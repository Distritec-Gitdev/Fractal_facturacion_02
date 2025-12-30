@props([
    'circular' => true,
    'size' => 'md',
])

@php
    $panelUser = \Filament\Facades\Filament::auth()->user();
    $authUser  = $panelUser ?? auth()->user();
    $srcAttr   = $attributes->get('src'); // lo que Filament pase (p. ej. ui-avatars)
    $src       = null;

    // 1) FOTO LOCAL con cache-busting
    $localUrl = null;
    if ($authUser) {
        foreach (['png','jpg','jpeg','webp'] as $ext) {
            $p = "avatars/{$authUser->id}.{$ext}";
            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($p)) {
                $ver = @filemtime(storage_path('app/public/' . $p)) ?: time();
                $localUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($p) . '?v=' . $ver;
                break;
            }
        }
    }

    // 2) Si hay local, usa local SIEMPRE; si no, usa src entrante (ui-avatars) o, si falta, iniciales.
    $src = $localUrl ?: $srcAttr;

    // 3) Iniciales si no hay src
    $initials = '';
    if (! $src && $authUser) {
        $name = trim($authUser->name ?? '');
        if ($name !== '') {
            $parts = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY);
            $initials = strtoupper(mb_substr($parts[0] ?? '', 0, 1) . mb_substr($parts[1] ?? '', 0, 1));
        } else {
            $initials = strtoupper(mb_substr((string) ($authUser->email ?? ''), 0, 1));
        }
    }

    $sizeClasses = match ($size) {
        'sm' => 'h-6 w-6',
        'md' => 'h-8 w-8',
        'lg' => 'h-10 w-10',
        default => $size,
    };

    $shapeClasses = $circular ? 'fi-circular rounded-full' : 'rounded-md';
@endphp

@if ($src)
    <img
        src="{{ $src }}"
        {{
            $attributes
                ->except('src')
                ->class([
                    'fi-avatar object-cover object-center',
                    $shapeClasses,
                    $sizeClasses,
                ])
        }}
    />
@else
    <div
        {{
            $attributes
                ->class([
                    'fi-avatar flex items-center justify-center font-semibold select-none',
                    'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
                    $shapeClasses,
                    $sizeClasses,
                ])
        }}
        aria-label="Avatar"
        role="img"
    >
        {{ $initials }}
    </div>
@endif
