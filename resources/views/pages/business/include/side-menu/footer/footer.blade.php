<div class="aside-footer d-flex flex-column align-items-center flex-column-auto" id="kt_aside_footer">
	<!--begin::User-->
	<div class="d-flex align-items-center mb-10" id="kt_header_user_menu_toggle">
		<!--begin::Menu wrapper-->
		<div class="cursor-pointer symbol symbol-40px" data-kt-menu-trigger="click" data-kt-menu-overflow="true" data-kt-menu-placement="top-start" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-dismiss="click" title="User profile">
			@if(auth()->check() && auth()->user()->avatar == null)
			<!-- <img src="{{asset('include/dashboard/media/avatars/blank.png')}}" alt="image" /> -->
			<identicon-svg username="{{auth()->user()->first_name}} {{auth()->user()->last_name}}"></identicon-svg>
			@else
			<img src="{{ auth()->user()->avatar_url }}" alt="Your profile photo" />
			@endif
		</div>
		<!--begin::Menu-->
		<div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-800 menu-state-bg menu-state-primary fw-bold py-4 fs-6 w-275px" data-kt-menu="true">
			<!--begin::Menu item-->
			<div class="menu-item px-3">
				<div class="menu-content d-flex align-items-center px-3">
					<!--begin::Avatar-->
					<div class="symbol symbol-50px me-5">
						@if(auth()->check() && auth()->user()->avatar == null)
						<!-- <img alt="Logo" src="{{asset('include/dashboard/media/avatars/blank.png')}}" alt="image" /> -->
						<identicon-svg username="{{auth()->user()->first_name}} {{auth()->user()->last_name}}"></identicon-svg>
						@else
						<img src="{{ auth()->user()->avatar_url }}" alt="Your profile photo" />
						@endif
					</div>
					<!--end::Avatar-->
					<!--begin::Username-->
					<div class="d-flex flex-column">
						<div class="fw-bolder d-flex align-items-center fs-5">
							@if(auth()->check() && auth()->user()->business->account_type == 'individual')
								{{auth()->user()->first_name}} {{auth()->user()->last_name}}
							@else
								{{auth()->user()->business->business_name}}
							@endif
						</div>
						<a href="#" class="fw-bold text-muted text-hover-primary fs-7">
							@if(auth()->check() && auth()->user()->business->account_type == 'individual')
								{{auth()->user()->email}}
							@else
								{{auth()->user()->business->business_email}}
							@endif
						</a>
					</div>
					<!--end::Username-->
				</div>
			</div>
			<!--end::Menu item-->
			<!--begin::Menu separator-->
			<div class="separator my-2"></div>
			<!--end::Menu separator-->
			<!--begin::Menu item-->
			<div class="menu-item px-5">
				<a href="{{ route('business.profile.show') }}" class="menu-link px-5 {{ request()->is('my-profile') ? 'active' : '' }}">My profile</a>
			</div>
			<div class="menu-item px-5">
				<a href="{{ route('support.tickets.index') }}" class="menu-link px-5 {{ request()->is('support/tickets*') ? 'active' : '' }}">My support tickets</a>
			</div>
			@if(\App\Services\Agents\AgentProfileService::current())
			<div class="menu-item px-5">
				<a href="{{ route('business.my_agent') }}" class="menu-link px-5 {{ request()->is('my-agent') ? 'active' : '' }}">My agent dashboard</a>
			</div>
			@endif
			<!--end::Menu item-->
			@if(auth()->user()->hasBusinessPermission('subaccount_management', 'view') || auth()->user()->hasBusinessPermission('role_management', 'view'))
			<!--begin::Menu item-->
			<div class="menu-item px-5">
				<a href="{{ route('business.team.index') }}" class="menu-link px-5 {{ request()->is('business_account/team') ? 'active' : '' }}">Team &amp; roles</a>
			</div>
			<!--end::Menu item-->
			@endif
			@if(auth()->user()->isPlatformAdmin())
			<div class="menu-item px-5">
				<a href="{{ route('platform.index') }}" class="menu-link px-5 {{ request()->is('platform*') ? 'active' : '' }}">Platform administration</a>
			</div>
			@endif
			@module('listings')
			@businessCan('listings_management')
			<!--begin::Menu item-->
			<div class="menu-item px-5">
				<a href="{{ route('listings.index') }}" class="menu-link px-5  {{ (request()->is('listings*')) ? 'active' : '' }}">
					<span class="menu-text">Listings</span>
					<span class="menu-badge" title="Published listings">
						<span class="badge badge-light-success fw-bolder fs-7">{{ \App\Models\Listing::where('user_id', business_owner_id())->published()->count() }}</span>
					</span>
				</a>
			</div>
			<!--end::Menu item-->
			@endbusinessCan
			@endmodule
			<!--begin::Menu separator-->
			<div class="separator my-2"></div>
			<!--end::Menu separator-->
			@businessCan('activity_log', 'view')
			<div class="menu-item px-5">
				<a href="{{ route('activity.index') }}" class="menu-link px-5 {{ request()->is('activity') ? 'active' : '' }}">Activity log</a>
			</div>
			@endbusinessCan
			@businessCan('account_settings', 'view')
			<!--begin::Menu item-->
			<div class="menu-item px-5 my-1">
				<a href="/business_account" class="menu-link px-5 {{ (request()->is('business_account')) ? 'active' : '' }}">Business settings</a>
			</div>
			<!--end::Menu item-->
			@endbusinessCan
			<!--begin::Menu item-->
			<div class="menu-item px-5">
				
				<a href="{{ route('logout') }}"
						class="menu-link px-5"
						onclick="event.preventDefault();
									document.getElementById('logout-form').submit();">
						{{ __('Sign Out') }}
					</a>
				<form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
					@csrf
				</form>
			</div>
			<!--end::Menu item-->
		</div>
		<!--end::Menu-->
		<!--end::Menu wrapper-->
	</div>
	<!--end::User-->
</div>