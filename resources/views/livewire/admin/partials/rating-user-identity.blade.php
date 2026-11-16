@php
    $identityUser = $rating->syntheticUser;
    $identityProfile = data_get($rating->data, 'planning.synthetic_user_profile', []);
    $identityName = $identityUser?->name ?: data_get($identityProfile, 'name', 'Benutzer noch offen');
    $identityUsername = $identityUser?->username ?: data_get($identityProfile, 'username');
    $identityBadges = $nameQualityBadges[$identityUser?->id] ?? [];
@endphp
<div class="rating-identity">
    <span class="rating-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($identityName, 0, 2)) }}</span>
    <div class="min-w-0">
        <div class="rating-person-name">{{ $identityName }}</div>
        @if($identityUsername)<div class="rating-username">{{ '@'.$identityUsername }}</div>@endif
        @if($identityUser)<div class="rating-meta mt-1">Benutzer #{{ $identityUser->id }}</div>@endif
        @if($identityBadges)
            <div class="mt-2 flex flex-wrap gap-1.5">
                @foreach($identityBadges as $badge)
                    <button type="button" wire:click.stop="$set('filterNameQuality', '{{ $badge['type'] }}')"
                        x-on:keydown.stop class="rating-name-hint" title="{{ $badge['title'] }} · Liste danach filtern"
                        aria-label="{{ $badge['label'] }}: Bewertungen danach filtern">
                        <i class="fal fa-clone" aria-hidden="true"></i>{{ $badge['label'] }}
                    </button>
                @endforeach
            </div>
        @endif
    </div>
</div>
