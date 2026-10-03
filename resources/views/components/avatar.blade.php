@props(['user', 'size' => 'size-10'])
@if ($user->avatar_path)
    <img src="{{ route('assets.avatar', $user) }}" alt="" {{ $attributes->class([$size, 'shrink-0 rounded-full object-cover']) }}>
@else
    <span {{ $attributes->class([$size, 'grid shrink-0 place-items-center rounded-full bg-brand-100 font-bold text-brand-700']) }}>{{ strtoupper(substr($user->name, 0, 1)) }}</span>
@endif
