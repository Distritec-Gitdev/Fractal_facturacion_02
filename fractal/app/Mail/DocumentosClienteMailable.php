<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Attachment;

class DocumentosClienteMailable extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Variables para la vista del correo.
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * Adjuntos cifrados (preferidos).
     * @var array<int, array{path:string, name?:string, mime?:string}>
     */
    protected array $attachmentsEncrypted = [];

    /**
     * Adjuntos de respaldo (persistidos) si no hay cifrados.
     * @var array<int, array{path:string, name?:string, mime?:string}>
     */
    protected array $attachmentsFallback = [];

    /**
     * Constructor.
     * Ejemplo de uso:
     *  new DocumentosClienteMailable($viewData, $attachmentsEncrypted, $attachmentsFallback)
     */
    public function __construct(
        array $viewData,
        array $attachmentsEncrypted = [],
        array $attachmentsFallback = []
    ) {
        $this->data = $viewData;
        $this->attachmentsEncrypted = $this->normalizeFiles($attachmentsEncrypted);
        $this->attachmentsFallback  = $this->normalizeFiles($attachmentsFallback);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->data['subject'] ?? 'Documentación de tu compra - Distritec'
        );
    }

    public function content(): Content
    {
        // Defaults seguros por si algo no viene en $viewData
        $with = [
            'clienteNombre' => $this->data['clienteNombre'] ?? 'Cliente',
            'logoUrl'       => $this->data['logoUrl']       ?? asset('imagenes_cabecera_pdf/logo.png'),
            'backgroundUrl' => $this->data['backgroundUrl'] ?? asset('imagenes_cabecera_pdf/Izquierda2.png'),
            'instagramLink' => $this->data['instagramLink'] ?? 'https://instagram.com/distriteccolombia',
            'whatsappLink'  => $this->data['whatsappLink']  ?? 'https://wa.me/573136200202',
            'soporteLink'   => $this->data['soporteLink']   ?? 'https://distritec.co',
        ];

        return new Content(
            view: 'emails.documentos-cliente', // <-- asegúrate de que este blade exista
            with: $with,
        );
    }

    public function attachments(): array
    {
        $out = [];

        // 1) Prioriza cifrados; 2) si no hay, usa fallback
        $files = !empty($this->attachmentsEncrypted)
            ? $this->attachmentsEncrypted
            : $this->attachmentsFallback;

        foreach ($files as $f) {
            if (!empty($f['path']) && is_file($f['path'])) {
                $att = Attachment::fromPath($f['path'])
                    ->as($f['name'] ?? basename($f['path']));
                if (!empty($f['mime'])) {
                    $att = $att->withMime($f['mime']);
                }
                $out[] = $att;
            }
        }

        return $out;
    }

    /**
     * Normaliza entradas de adjuntos (string o array) y elimina duplicados por ruta real.
     *
     * @param array<int, string|array{path:string,name?:string,mime?:string}> $files
     * @return array<int, array{path:string,name:string,mime:?string}>
     */
    protected function normalizeFiles(array $files): array
    {
        $map = [];

        foreach ($files as $item) {
            if (is_string($item)) {
                $path = $item;
                $name = basename($item);
                $mime = $this->guessMime($item);
            } elseif (is_array($item) && isset($item['path'])) {
                $path = $item['path'];
                $name = $item['name'] ?? basename($path);
                $mime = $item['mime'] ?? $this->guessMime($path);
            } else {
                continue;
            }

            if (!$path) continue;

            $key = strtolower(realpath($path) ?: $path); // dedupe por ruta real
            $map[$key] = [
                'path' => $path,
                'name' => $name,
                'mime' => $mime,
            ];
        }

        return array_values($map);
    }

    protected function guessMime(string $path): ?string
    {
        $mime = @mime_content_type($path) ?: null;
        if ($mime) return $mime;

        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'pdf'         => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            default       => null,
        };
    }
}
