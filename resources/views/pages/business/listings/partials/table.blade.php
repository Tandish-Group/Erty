{{-- Unit-type listings as a table. Needs $rows (Listings with complex, complexUnit, commercialUnit, photos),
     $availability [listing id => units free], and optionally $showProperty, $canPublish. --}}
@php
    $statusBadge = [\App\Models\Listing::DRAFT => 'badge-light', \App\Models\Listing::PUBLISHED => 'badge-light-success', \App\Models\Listing::UNPUBLISHED => 'badge-light-warning'];
    $money = fn ($amount, $currency) => $amount === null ? null : $currency . ' ' . number_format($amount);
@endphp
<div class="tenant-table-scroll" role="region" aria-label="Listings" tabindex="0">
    <table class="tenant-table" data-listings-table>
        <thead><tr><th>Listing</th><th>Available</th><th>Price</th><th>Photos</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @foreach($rows as $listing)
            @php
                $free = $availability[$listing->id] ?? 0;
                $total = $listing->isCommercial() ? 1 : (int) $listing->units;
                $missing = $listing->missingForPublishing();
            @endphp
            <tr data-listing-row="{{ $listing->ref }}">
                <td>
                    <strong>{{ $listing->name }}</strong>
                    <small>
                        @if(!empty($showProperty)){{ $listing->complex?->title }} · @endif
                        {{ $listing->ref }}@if($listing->sq_area) · {{ number_format($listing->sq_area) }} sq ft @endif
                    </small>
                </td>
                <td>
                    <strong class="{{ $free > 0 ? 'text-success' : 'text-muted' }}">{{ $free }}</strong> of {{ $total }}
                    <small>{{ $listing->isCommercial() ? ($free ? 'Space free' : 'No space free') : ($listing->type === 'House' ? ($free === 1 ? 'house free' : 'houses free') : ($free === 1 ? 'unit vacant' : 'units vacant')) }}</small>
                </td>
                <td>
                    @if(in_array($listing->offer ?: 'rent', ['rent', 'both'], true))
                    {{ $money($listing->amount, $listing->currency) ?? '—' }} <span class="text-muted fs-8">/ month</span>
                    <small>{{ $listing->deposit !== null ? 'Deposit ' . $money($listing->deposit, $listing->currency) : 'No deposit set' }}</small>
                    @endif
                    @if(in_array($listing->offer, ['sale', 'both'], true))
                    <div>{{ $money($listing->sale_price, $listing->currency) ?? 'Asking price not set' }}</div><small>for sale</small>
                    @endif
                </td>
                <td>{{ $listing->photos->count() }}</td>
                <td>
                    <span class="badge {{ $statusBadge[$listing->status] ?? 'badge-light' }}">{{ $listing->status }}</span>
                    @if($listing->isPublished() && $free === 0)<small>Hidden from renters until a unit is vacant</small>
                    @elseif(!$listing->isPublished() && $missing)<small>Needs {{ implode(', ', $missing) }}</small>@endif
                </td>
                <td class="text-end text-nowrap">
                    <a class="btn btn-sm btn-light-primary" href="{{ route('listings.edit', $listing->id) }}">{{ !empty($canEdit) ? 'Edit' : 'Open' }}</a>
                    @if(!empty($canPublish))
                        @if($listing->isPublished())
                            <form method="post" action="{{ route('listings.unpublish', $listing->id) }}" class="d-inline">@csrf<button class="btn btn-sm btn-light">Unpublish</button></form>
                        @elseif(!$missing)
                            <form method="post" action="{{ route('listings.publish', $listing->id) }}" class="d-inline">@csrf<button class="btn btn-sm btn-primary">Publish</button></form>
                        @endif
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
