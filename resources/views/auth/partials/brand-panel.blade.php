{{-- Brand panel of the sign-in screens (shown on the left; hidden on small screens). Decorative. --}}
<aside class="auth-split__panel" aria-label="About {{ config('app.name') }}">
    <div class="auth-split__panel-inner">
        <div>
            <span class="auth-split__eyebrow">Property management</span>
            <h2 class="auth-split__headline">Every property, unit and tenant in one place.</h2>
            <p>See which units are let and which are free, bill rent on time and keep repairs moving, with your whole team working from the same records.</p>
        </div>

        <div class="auth-split__card" aria-hidden="true">
            <div class="auth-split__card-head">
                <div style="flex:1"><span class="auth-split__bar auth-split__bar--title"></span><span class="auth-split__bar auth-split__bar--sub"></span></div>
                <span class="auth-split__chip">Units</span>
            </div>
            @php $pattern = 'oooovooo' . 'ooroooov' . 'oovooooo' . 'oooooovo'; @endphp
            <div class="auth-split__units">
                @foreach(str_split($pattern) as $u)<span class="{{ $u === 'v' ? 'is-vacant' : ($u === 'r' ? 'is-repair' : '') }}"></span>@endforeach
            </div>
            <div class="auth-split__progress"><span></span></div>
            <div class="auth-split__legend"><span><i></i>Let</span><span><i class="is-vacant"></i>Vacant</span><span><i class="is-repair"></i>Under repair</span></div>
        </div>

        <ul class="auth-split__points">
            <li>Units and occupancy, always up to date</li>
            <li>Rent billing, payments and statements</li>
            <li>Maintenance, leads and agents</li>
        </ul>
    </div>
</aside>
