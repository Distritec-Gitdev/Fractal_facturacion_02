<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Models\Chat;
use App\Models\Cliente;
use App\Events\NewMessageSent;

class ChatController extends Controller
{
    public function unreadCount($clientId)
    {
        $clientId = (int) $clientId;
        $me = (int) Auth::id();

        $count = Chat::where('id_cliente', $clientId)
            ->whereNull('read_at')
            ->where(function ($q) use ($me) {
                $q->whereNull('user_id')
                  ->orWhere('user_id', '!=', $me);
            })
            ->count();

        return response()->json(['unread' => $count]);
    }

    public function getMessages($clientId)
    {
        $clientId = (int) $clientId;
        $me = (int) Auth::id();

        $cliente = Cliente::find($clientId);
        if (!$cliente) {
            return response()->json(['error' => 'Cliente no encontrado'], 404);
        }

        $rows = Chat::where('id_cliente', $clientId)
            ->with(['user:id,name'])
            ->orderBy('id', 'asc')
            ->get();

        // ✅ 1) Detectar último mensaje NO mío (lo que yo acabo de "ver")
        $lastIncomingId = 0;
        foreach ($rows as $m) {
            $uid = (int) ($m->user_id ?? 0);
            $isMine = ($uid > 0 && $uid === $me); // sin roles, solo por user_id

            if (!$isMine) {
                $lastIncomingId = max($lastIncomingId, (int) $m->id);
            }
        }

        // ✅ 2) Marcar read_at en BD (si existe) y emitir SEEN a los demás
        if ($lastIncomingId > 0) {

            // (Opcional pero recomendado) marcar como leído en DB si tienes read_at
            try {
                Chat::where('id_cliente', $clientId)
                    ->where('id', '<=', $lastIncomingId)
                    ->whereNull('read_at')
                    ->where(function ($q) use ($me) {
                        $q->whereNull('user_id')
                          ->orWhere('user_id', '!=', $me);
                    })
                    ->update(['read_at' => now()]);
            } catch (\Throwable $e) {
                // Si no tienes read_at, no rompemos nada. Solo seguimos con SEEN.
            }

            // Emitir evento "seen" (sin eventos nuevos, usamos el tuyo)
            $dummy = new Chat();
            $dummy->id = $lastIncomingId;
            $dummy->message = '';

            try {
                broadcast(new NewMessageSent(
                    clientId: $clientId,
                    message: $dummy,
                    kind: 'seen',
                    peerLastSeenId: $lastIncomingId,
                    readerId: $me
                ))->toOthers();
            } catch (\Throwable $e) {}
        }

        $messages = $rows->map(function ($message) use ($me) {
            $text = (string) ($message->message ?? $message->mensaje ?? '');
            $uid  = (int) ($message->user_id ?? 0);

            return [
                'id'          => (int) $message->id,
                'message'     => $text,
                'created_at'  => optional($message->created_at)->toDateTimeString(),
                'user'        => $message->user ? $message->user->only(['id', 'name']) : null,
                'is_mine'     => ($uid > 0 && $uid === $me),
            ];
        });

        $clientName = $cliente->nombre_completo ?: 'Cliente #' . $clientId;

        return response()->json([
            'messages'   => $messages,
            'clientName' => $clientName
        ]);
    }

    public function sendMessage(Request $request)
    {
        $request->validate([
            'clientId' => 'required|integer',
            'message'  => 'required|string|max:1000',
        ]);

        $clientId = (int) $request->clientId;

        $cliente = Cliente::find($clientId);
        if (!$cliente) {
            return response()->json(['error' => 'Cliente no encontrado'], 404);
        }

        $chat = Chat::create([
            'id_cliente' => $clientId,
            'user_id'    => Auth::id(),
            'message'    => trim($request->message),
        ]);

        $chat->loadMissing('user:id,name');

        try {
            broadcast(new NewMessageSent(
                clientId: $clientId,
                message: $chat,
                kind: 'message',
                peerLastSeenId: null,
                readerId: null
            ))->toOthers();
        } catch (\Throwable $e) {}

        return response()->json([
            'success' => true,
            'message' => 'Mensaje enviado correctamente'
        ]);
    }
}
