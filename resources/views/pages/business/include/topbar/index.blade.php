<!--begin::Topbar-->
<div class="d-flex flex-shrink-0">
	@businessCan('complex_management')
	<!--begin::Create Complex-->
	<div class="d-flex ms-3">
		<a href="/complex" class="btn btn-flex flex-center bg-body btn-color-gray-700 btn-active-color-primary w-40px w-md-auto h-40px px-0 px-md-6 {{ (request()->is('complex*')) ? 'active' : '' }}" tooltip="New Complex" data-tour-nav="new-complex">
			<!--begin::Svg Icon | path: icons/duotune/arrows/arr075.svg-->
			<span class="svg-icon svg-icon-2 svg-icon-primary me-0 me-md-2">
				<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
					<path opacity="0.3" d="M5 8.04999L11.8 11.95V19.85L5 15.85V8.04999Z" fill="black"/>
					<path d="M20.1 6.65L12.3 2.15C12 1.95 11.6 1.95 11.3 2.15L3.5 6.65C3.2 6.85 3 7.15 3 7.45V16.45C3 16.75 3.2 17.15 3.5 17.25L11.3 21.75C11.5 21.85 11.6 21.85 11.8 21.85C12 21.85 12.1 21.85 12.3 21.75L20.1 17.25C20.4 17.05 20.6 16.75 20.6 16.45V7.45C20.6 7.15 20.4 6.75 20.1 6.65ZM5 15.85V7.95L11.8 4.05L18.6 7.95L11.8 11.95V19.85L5 15.85Z" fill="black"/>
				</svg>
			</span>
			<!--end::Svg Icon-->
			<span class="d-none d-md-inline">Property portfolio</span>
		</a>
	</div>
	<!--end::Create Complex-->
	@endbusinessCan
	@module('listings')
	@businessCan('listings_management')
	<!--begin::Create Listing-->
	<div class="d-flex ms-3">
		<a href="/listings" class="btn btn-flex flex-center bg-body btn-color-gray-700 btn-active-color-primary w-40px w-md-auto h-40px px-0 px-md-6 {{ (request()->is('listing*')) ? 'active' : '' }}" tooltip="Listings" id="kt_toolbar_primary_button" data-tour-nav="new-listing">
			<!--begin::Svg Icon | path: icons/duotune/general/gen005.svg-->
			<span class="svg-icon svg-icon-2 svg-icon-primary me-0 me-md-2">
				<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
					<path opacity="0.3" d="M19 22H5C4.4 22 4 21.6 4 21V3C4 2.4 4.4 2 5 2H14L20 8V21C20 21.6 19.6 22 19 22ZM15 17C15 16.4 14.6 16 14 16H8C7.4 16 7 16.4 7 17C7 17.6 7.4 18 8 18H14C14.6 18 15 17.6 15 17ZM17 12C17 11.4 16.6 11 16 11H8C7.4 11 7 11.4 7 12C7 12.6 7.4 13 8 13H16C16.6 13 17 12.6 17 12ZM17 7C17 6.4 16.6 6 16 6H8C7.4 6 7 6.4 7 7C7 7.6 7.4 8 8 8H16C16.6 8 17 7.6 17 7Z" fill="black" />
					<path d="M15 8H20L14 2V7C14 7.6 14.4 8 15 8Z" fill="black" />
				</svg>
			</span>
			<!--end::Svg Icon-->
			<span class="d-none d-md-inline">Listings</span>
		</a>
	</div>
	<!--end::Create Listing-->
	@endbusinessCan
	@endmodule
	<!--begin::Chat-->
	<div class="d-flex align-items-center ms-3">
		<!--begin::Menu wrapper-->
		@php $bellUnread = auth()->user()->unreadNotifications()->count(); @endphp
		<button type="button" class="btn btn-icon btn-primary w-40px h-40px notif-bell {{ $bellUnread ? 'has-unread' : '' }}" id="kt_drawer_chat_toggle" aria-label="Notifications{{ $bellUnread ? ', ' . $bellUnread . ' unread' : '' }}">
			<i class="bi bi-bell fs-3 text-white" aria-hidden="true"></i>
			<span class="notif-count" data-notif-count @if(!$bellUnread) hidden @endif>{{ $bellUnread > 99 ? '99+' : $bellUnread }}</span>
		</button>
		<!--end::Menu wrapper-->
	</div>
	<!--end::Chat-->
</div>
<!--end::Topbar-->
