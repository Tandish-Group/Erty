{{-- Why the commission is what it is (shared by the full commission card and the compact note on My agent). --}}
<p class="fs-7 text-muted mb-0">
            @if($type)
                Calculated as <strong>{{ $typeLabels[$type] ?? str_replace('_', ' ', $type) }}</strong>
                @if($type === 'flat_rate') at {{ $money($commission['commission_rate']) }} per paying tenancy @elseif($type !== 'tiered') at {{ rtrim(rtrim(number_format($commission['commission_rate'], 2), '0'), '.') }}% @endif.
                @if($type === 'tiered' && !$commission['has_tiers']) No tiers are set up yet, so the commission is 0. @endif
            @else
                No commission agreement covers this agent yet. An administrator sets one under Agents → Commissions → Agreements.
            @endif
            @if(!empty($commission['agreement']))Current terms: {{ $commission['agreement'] }}.@endif
            Recorded from rent collected (confirmed payments, and deposit applied at move-out) on the units this agent was responsible for when the money came in, at the rates in force then. Deposits, late fees, service charges and reversed payments don't count unless an agreement says so.
            @if(($commission['pending_commission'] ?? 0) > 0) {{ $money($commission['pending_commission']) }} more is waiting for approval. @endif
            @if(!empty($statementUrl)) <a href="{{ $statementUrl }}" data-commission-statement-link>Open the statement and payouts</a>. @endif
            @if(($commission['base_salary'] ?? 0) > 0) Base salary ({{ $money($commission['base_salary']) }}) is paid separately. @endif
        </p>
