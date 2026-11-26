<div id="kt_aside" class="aside" data-kt-drawer="true" data-kt-drawer-name="aside" data-kt-drawer-activate="{default: true, lg: false}" data-kt-drawer-overlay="true" data-kt-drawer-width="260px" data-kt-drawer-direction="start" data-kt-drawer-toggle="#kt_aside_toggle">
	<!--begin::Primary-->
	<div class="aside-primary d-flex flex-column align-items-lg-center flex-row-auto">
		<!--begin::Logo-->
		<div class="aside-logo d-none d-lg-flex flex-column align-items-center flex-column-auto py-10" id="kt_aside_logo">
			<a href="/dashboard" title="{{ config('app.name') }}">
				<identicon-svg username="Application {{ config('app.name') }}"></identicon-svg>
			</a>
		</div>
		<!--end::Logo-->
		<!--begin::Nav-->
		<div class="aside-nav d-flex flex-column align-items-center flex-column-fluid w-100 pt-5 pt-lg-0" id="kt_aside_nav">
			<!--begin::Wrapper-->
			<div class="mb-10" data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-height="auto" data-kt-scroll-wrappers="#kt_aside_nav" data-kt-scroll-dependencies="#kt_aside_logo, #kt_aside_footer" data-kt-scroll-offset="0px">
				<!--begin::Nav-->
				<ul class="nav flex-column">
					<!--begin::Nav item-->
					@businessCan('dashboard_access')
					<li class="nav-item mb-6" data-bs-toggle="tooltip" data-bs-trigger="hover" data-bs-placement="right" data-bs-dismiss="click" title="Dashboard" data-tour-nav="dashboard">
						<!--begin::Nav link-->
						<a class="nav-link btn btn-icon btn-active-color-primary btn-color-gray-400 d-flex flex-column  @if(request()->is('dashboard')) active @endif" href="/dashboard">
							<!--begin::Svg Icon | path: assets/media/icons/duotune/general/gen001.svg-->
							<span class="svg-icon svg-icon-2x">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
									<path d="M11 2.375L2 9.575V20.575C2 21.175 2.4 21.575 3 21.575H9C9.6 21.575 10 21.175 10 20.575V14.575C10 13.975 10.4 13.575 11 13.575H13C13.6 13.575 14 13.975 14 14.575V20.575C14 21.175 14.4 21.575 15 21.575H21C21.6 21.575 22 21.175 22 20.575V9.575L13 2.375C12.4 1.875 11.6 1.875 11 2.375Z" fill="currentColor"/>
								</svg>
							</span>
							<!--end::Svg Icon-->
							<span class="aside-nav-label fw-semibold">Home</span>
						</a>
						<!--end::Nav link-->
					</li>
					@endbusinessCan
					<!--end::Nav item-->
					<!--begin::Nav item-->
					<!--end::Nav item-->
					<!--begin::Nav item-->
					@if(auth()->user()->hasBusinessPermission('crm_management', 'view'))
					<li class="nav-item mb-6" data-bs-toggle="tooltip" data-bs-trigger="hover" data-bs-placement="right" data-bs-dismiss="click" title="CRM — Leads" data-tour-nav="crm">
						<!--begin::Nav link-->
						<a class="nav-link btn btn-icon btn-active-color-primary btn-color-gray-400 d-flex flex-column  @if(request()->is('customer_relationship') || request()->is('customer_relationship/*')) active @endif" href="/customer_relationship">
							<!--begin::Svg Icon | path: assets/media/icons/duotune/general/gen001.svg-->
							<span class="svg-icon svg-icon-2x">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
									<path opacity="0.3" d="M3 6C2.4 6 2 5.6 2 5V3C2 2.4 2.4 2 3 2H5C5.6 2 6 2.4 6 3C6 3.6 5.6 4 5 4H4V5C4 5.6 3.6 6 3 6ZM22 5V3C22 2.4 21.6 2 21 2H19C18.4 2 18 2.4 18 3C18 3.6 18.4 4 19 4H20V5C20 5.6 20.4 6 21 6C21.6 6 22 5.6 22 5ZM6 21C6 20.4 5.6 20 5 20H4V19C4 18.4 3.6 18 3 18C2.4 18 2 18.4 2 19V21C2 21.6 2.4 22 3 22H5C5.6 22 6 21.6 6 21ZM22 21V19C22 18.4 21.6 18 21 18C20.4 18 20 18.4 20 19V20H19C18.4 20 18 20.4 18 21C18 21.6 18.4 22 19 22H21C21.6 22 22 21.6 22 21ZM16 11V9C16 6.8 14.2 5 12 5C9.8 5 8 6.8 8 9V11C7.2 11 6.5 11.7 6.5 12.5C6.5 13.3 7.2 14 8 14V15C8 17.2 9.8 19 12 19C14.2 19 16 17.2 16 15V14C16.8 14 17.5 13.3 17.5 12.5C17.5 11.7 16.8 11 16 11ZM13.4 15C13.7 15 14 15.3 13.9 15.6C13.6 16.4 12.9 17 12 17C11.1 17 10.4 16.5 10.1 15.7C10 15.4 10.2 15 10.6 15H13.4Z" fill="black"/>
									<path d="M9.2 12.9C9.1 12.8 9.10001 12.7 9.10001 12.6C9.00001 12.2 9.3 11.7 9.7 11.6C10.1 11.5 10.6 11.8 10.7 12.2C10.7 12.3 10.7 12.4 10.7 12.5L9.2 12.9ZM14.8 12.9C14.9 12.8 14.9 12.7 14.9 12.6C15 12.2 14.7 11.7 14.3 11.6C13.9 11.5 13.4 11.8 13.3 12.2C13.3 12.3 13.3 12.4 13.3 12.5L14.8 12.9ZM16 7.29998C16.3 6.99998 16.5 6.69998 16.7 6.29998C16.3 6.29998 15.8 6.30001 15.4 6.20001C15 6.10001 14.7 5.90001 14.4 5.70001C13.8 5.20001 13 5.00002 12.2 4.90002C9.9 4.80002 8.10001 6.79997 8.10001 9.09997V11.4C8.90001 10.7 9.40001 9.8 9.60001 9C11 9.1 13.4 8.69998 14.5 8.29998C14.7 9.39998 15.3 10.5 16.1 11.4V9C16.1 8.5 16 8 15.8 7.5C15.8 7.5 15.9 7.39998 16 7.29998Z" fill="black"/>
								</svg>
							</span>
							<!--end::Svg Icon-->
							<span class="aside-nav-label fw-semibold">CRM</span>
						</a>
						<!--end::Nav link-->
					</li>
					@endif
					<!--end::Nav item-->
					<!--begin::Nav item-->
					@if(auth()->user()->hasBusinessPermission('agent_management', 'view') || auth()->user()->hasBusinessPermission('task_management', 'view'))
					<li class="nav-item mb-6" data-bs-toggle="tooltip" data-bs-trigger="hover" data-bs-placement="right" data-bs-dismiss="click" title="Agents, tasks & commissions" data-tour-nav="agents">
						<a class="nav-link btn btn-icon btn-active-color-primary btn-color-gray-400 d-flex flex-column @if(request()->is('agents') || request()->is('agents/*')) active @endif" href="{{ auth()->user()->hasBusinessPermission('agent_management', 'view') ? '/agents' : '/agents/tasks' }}">
							<span class="svg-icon svg-icon-2x">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
									<path opacity="0.3" d="M20 15H4C2.9 15 2 14.1 2 13V7C2 6.4 2.4 6 3 6H21C21.6 6 22 6.4 22 7V13C22 14.1 21.1 15 20 15ZM13 12H11C10.5 12 10 12.4 10 13V16C10 16.5 10.4 17 11 17H13C13.6 17 14 16.6 14 16V13C14 12.4 13.6 12 13 12Z" fill="black"/>
									<path d="M14 6V5H10V6H8V5C8 3.9 8.9 3 10 3H14C15.1 3 16 3.9 16 5V6H14ZM20 15H14V16C14 16.6 13.5 17 13 17H11C10.5 17 10 16.6 10 16V15H4C3.6 15 3.3 14.9 3 14.7V18C3 19.1 3.9 20 5 20H19C20.1 20 21 19.1 21 18V14.7C20.7 14.9 20.4 15 20 15Z" fill="black"/>
								</svg>
							</span>
							<span class="aside-nav-label fw-semibold">Agents</span>
						</a>
					</li>
					@endif
					<!--end::Nav item-->
					<!--begin::Nav item-->
					@businessCan('tenant_management')
					<li class="nav-item mb-6" data-bs-toggle="tooltip" data-bs-trigger="hover" data-bs-placement="right" data-bs-dismiss="click" title="Tenants" data-tour-nav="tenants">
						<!--begin::Nav link-->
						<a class="nav-link btn btn-icon btn-active-color-primary btn-color-gray-400 d-flex flex-column  @if(request()->is('tenant_management*')) active @endif" href="/tenant_management">
							<!--begin::Svg Icon | path: icons/duotune/files/fil005.svg-->
							<span class="svg-icon svg-icon-2x">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
									<path d="M16.0173 9H15.3945C14.2833 9 13.263 9.61425 12.7431 10.5963L12.154 11.7091C12.0645 11.8781 12.1072 12.0868 12.2559 12.2071L12.6402 12.5183C13.2631 13.0225 13.7556 13.6691 14.0764 14.4035L14.2321 14.7601C14.2957 14.9058 14.4396 15 14.5987 15H18.6747C19.7297 15 20.4057 13.8774 19.912 12.945L18.6686 10.5963C18.1487 9.61425 17.1285 9 16.0173 9Z" fill="black"/>
									<rect opacity="0.3" x="14" y="4" width="4" height="4" rx="2" fill="black"/>
									<path d="M4.65486 14.8559C5.40389 13.1224 7.11161 12 9 12C10.8884 12 12.5961 13.1224 13.3451 14.8559L14.793 18.2067C15.3636 19.5271 14.3955 21 12.9571 21H5.04292C3.60453 21 2.63644 19.5271 3.20698 18.2067L4.65486 14.8559Z" fill="black"/>
									<rect opacity="0.3" x="6" y="5" width="6" height="6" rx="3" fill="black"/>
								</svg>
							</span>
							<!--end::Svg Icon-->
							<span class="aside-nav-label fw-semibold">Tenants</span>
						</a>
						<!--end::Nav link-->
					</li>
					@endbusinessCan
					<!--end::Nav item-->
					<!--begin::Nav item-->
					@module('sales')
					@if(auth()->user()->hasBusinessPermission('sales_management', 'view'))
					<li class="nav-item mb-6" data-bs-toggle="tooltip" data-bs-trigger="hover" data-bs-placement="right" data-bs-dismiss="click" title="Sales — houses sold and in progress" data-tour-nav="sales">
						<a class="nav-link btn btn-icon btn-active-color-primary btn-color-gray-400 d-flex flex-column @if(request()->is('sales') || request()->is('sales/*')) active @endif" href="{{ route('sales.index') }}">
							<span class="svg-icon svg-icon-2x">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
									<path opacity="0.3" d="M3 10.5L12 3L21 10.5V20C21 20.6 20.6 21 20 21H4C3.4 21 3 20.6 3 20V10.5Z" fill="black"/>
									<path d="M15.6 11.3C16 10.9 16 10.3 15.6 9.9C15.2 9.5 14.6 9.5 14.2 9.9L11 13.1L9.8 11.9C9.4 11.5 8.8 11.5 8.4 11.9C8 12.3 8 12.9 8.4 13.3L10.3 15.2C10.7 15.6 11.3 15.6 11.7 15.2L15.6 11.3Z" fill="black"/>
								</svg>
							</span>
							<span class="aside-nav-label fw-semibold">Sales</span>
						</a>
					</li>
					@endif
					@endmodule
					@businessCan('maintenance_management')
					<li class="nav-item mb-6" data-bs-toggle="tooltip" data-bs-trigger="hover" data-bs-placement="right" data-bs-dismiss="click" title="Maintenance — repairs and requests" data-tour-nav="maintenance">
						<a class="nav-link btn btn-icon btn-active-color-primary btn-color-gray-400 d-flex flex-column @if(request()->is('maintenance*')) active @endif" href="/maintenance">
							<span class="svg-icon svg-icon-2x">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
									<path opacity="0.3" d="M18.4 5.6C17 4.2 15 3.7 13.2 4.3L16 7.1L15.3 9.3L13.1 10L10.3 7.2C9.7 9 10.2 11 11.6 12.4C13 13.8 15 14.3 16.8 13.7L18.4 5.6Z" fill="black"/>
									<path d="M19.7 7.2L16.8 13.7C15.7 14.1 14.5 14.1 13.4 13.8L6.1 21.1C5.3 21.9 4 21.9 3.2 21.1L2.9 20.8C2.1 20 2.1 18.7 2.9 17.9L10.2 10.6C9.9 9.5 9.9 8.3 10.3 7.2L13.1 10L15.3 9.3L16 7.1L13.2 4.3C15.1 3.6 17.3 4.1 18.7 5.6C19.3 6.1 19.6 6.6 19.7 7.2Z" fill="black"/>
								</svg>
							</span>
							<span class="aside-nav-label fw-semibold">Maintenance</span>
						</a>
					</li>
					@endbusinessCan
					<!--end::Nav item-->
					<!--begin::Nav item-->
					@businessCan('legal_documents')
					<li class="nav-item mb-6" data-bs-toggle="tooltip" data-bs-trigger="hover" data-bs-placement="right" data-bs-dismiss="click" title="Legal — Contracts & Documents" data-tour-nav="legal">
						<!--begin::Nav link-->
						<a class="nav-link btn btn-icon btn-active-color-primary btn-color-gray-400 d-flex flex-column  @if(request()->is('legal')) active @endif" href="/legal">
							<!--begin::Svg Icon | path: icons/duotune/files/fil005.svg-->
							<span class="svg-icon svg-icon-2x">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
									<path opacity="0.3" d="M8.70001 6C8.10001 5.7 7.39999 5.40001 6.79999 5.10001C7.79999 4.00001 8.90001 3 10.1 2.2C10.7 2.1 11.4 2 12 2C12.7 2 13.3 2.1 13.9 2.2C12 3.2 10.2 4.5 8.70001 6ZM12 8.39999C13.9 6.59999 16.2 5.30001 18.7 4.60001C18.1 4.00001 17.4 3.6 16.7 3.2C14.4 4.1 12.2 5.40001 10.5 7.10001C11 7.50001 11.5 7.89999 12 8.39999Z" fill="black"/>
									<path d="M7 20C7 20.2 7 20.4 7 20.6C6.2 20.1 5.49999 19.6 4.89999 19C4.59999 18 4.00001 17.2 3.20001 16.6C2.80001 15.8 2.49999 15 2.29999 14.1C4.99999 14.7 7 17.1 7 20ZM10.6 9.89995C8.70001 8.09995 6.39999 6.89996 3.79999 6.29996C3.39999 6.89996 2.99999 7.49995 2.79999 8.19995C5.39999 8.59995 7.7 9.79996 9.5 11.6C9.8 10.9 10.2 10.3999 10.6 9.89995ZM2.20001 10.1C2.10001 10.7 2 11.4 2 12C2 12 2 12 2 12.1C4.3 12.4 6.40001 13.7 7.60001 15.6C7.80001 14.8 8.09999 14.0999 8.39999 13.3999C6.89999 11.5999 4.70001 10.4 2.20001 10.1ZM11 20C11 14 15.4 8.99996 21.2 8.09996C20.9 7.39996 20.6 6.79995 20.2 6.19995C13.8 7.49995 9 13.0999 9 19.8999C9 20.3999 9.00001 21 9.10001 21.5C9.80001 21.7 10.5 21.7999 11.2 21.8999C11.1 21.2999 11 20.7 11 20ZM19.1 19C19.4 18 20 17.2 20.8 16.6C21.2 15.8 21.5 15 21.7 14.1C19 14.7 16.9 17.1 16.9 20C16.9 20.2 16.9 20.4 16.9 20.6C17.8 20.2 18.5 19.6 19.1 19ZM15 20C15 15.9 18.1 12.6 22 12.1C22 12.1 22 12.1 22 12C22 11.3 21.9 10.7 21.8 10.1C16.8 10.7 13 14.9 13 20C13 20.7 13.1 21.2999 13.2 21.8999C13.9 21.7999 14.5 21.7 15.2 21.5C15.1 21 15 20.5 15 20Z" fill="black"/>
								</svg>
							</span>
							<!--end::Svg Icon-->
							<span class="aside-nav-label fw-semibold">Legal</span>
						</a>
						<!--end::Nav link-->
					</li>
					@endbusinessCan
					<!--end::Nav item-->
					@if(app(\App\Services\Reports\ReportRegistry::class)->availableTo(auth()->user())->isNotEmpty())
					<li class="nav-item mb-6" data-bs-toggle="tooltip" data-bs-trigger="hover" data-bs-placement="right" data-bs-dismiss="click" title="Reports" data-tour-nav="reports">
						<a class="nav-link btn btn-icon btn-active-color-primary btn-color-gray-400 d-flex flex-column @if(request()->is('reports') || request()->is('reports/*')) active @endif" href="{{ route('reports.index') }}">
							<span class="svg-icon svg-icon-2x">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
									<rect x="3" y="12" width="4" height="9" rx="1" fill="black"/>
									<rect opacity="0.3" x="10" y="3" width="4" height="18" rx="1" fill="black"/>
									<rect x="17" y="8" width="4" height="13" rx="1" fill="black"/>
								</svg>
							</span>
							<span class="aside-nav-label fw-semibold">Reports</span>
						</a>
					</li>
					@endif
					@if(!auth()->user()->isPlatformAdmin() && auth()->user()->answersSupport())
					<li class="nav-item mb-6" data-bs-toggle="tooltip" data-bs-trigger="hover" data-bs-placement="right" data-bs-dismiss="click" title="Support inbox" data-tour-nav="support">
						<a class="nav-link btn btn-icon btn-active-color-primary btn-color-gray-400 d-flex flex-column position-relative @if(request()->is('platform/support*')) active @endif" href="{{ route('platform.support.index') }}">
							<span class="svg-icon svg-icon-2x">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
									<path opacity="0.3" d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm0 6a4 4 0 1 1 0 8 4 4 0 0 1 0-8Z" fill="black"/>
									<path d="M6.3 4.9 9.4 8a4 4 0 0 0-1.4 1.4L4.9 6.3a8 8 0 0 1 1.4-1.4Zm11.4 0a8 8 0 0 1 1.4 1.4L16 9.4A4 4 0 0 0 14.6 8l3.1-3.1ZM8 14.6A4 4 0 0 0 9.4 16l-3.1 3.1a8 8 0 0 1-1.4-1.4L8 14.6Zm8 0 3.1 3.1a8 8 0 0 1-1.4 1.4L14.6 16a4 4 0 0 0 1.4-1.4Z" fill="black"/>
								</svg>
							</span>
							<span class="aside-nav-label fw-semibold">Support</span>
							@php $openSupport = \App\Models\SupportTicket::where('status', 'open')->count(); @endphp
							@if($openSupport)<span class="badge badge-circle badge-warning position-absolute top-0 end-0 mt-n1 me-n1" style="width:18px;height:18px;font-size:10px;" title="{{ $openSupport }} open support {{ \Illuminate\Support\Str::plural('ticket', $openSupport) }}">{{ $openSupport > 9 ? '9+' : $openSupport }}</span>@endif
						</a>
					</li>
					@endif
					@if(auth()->user()->isPlatformAdmin())
					<li class="nav-item mb-6" data-bs-toggle="tooltip" data-bs-trigger="hover" data-bs-placement="right" data-bs-dismiss="click" title="Platform administration" data-tour-nav="platform">
						<a class="nav-link btn btn-icon btn-active-color-primary btn-color-gray-400 d-flex flex-column position-relative @if(request()->is('platform') || request()->is('platform/*')) active @endif" href="{{ route('platform.index') }}">
							<span class="svg-icon svg-icon-2x">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true">
									<path opacity="0.3" d="M12 2 3 6v6c0 5 3.8 9.4 9 10 5.2-.6 9-5 9-10V6l-9-4Z" fill="black"/>
									<path d="M10.6 15.4 7.4 12.2l1.4-1.4 1.8 1.8 4.6-4.6 1.4 1.4-6 6Z" fill="black"/>
								</svg>
							</span>
							<span class="aside-nav-label fw-semibold">Platform</span>
							@php $openSupport = \App\Models\SupportTicket::whereIn('status', ['open'])->count(); @endphp
							@if($openSupport)<span class="badge badge-circle badge-warning position-absolute top-0 end-0 mt-n1 me-n1" style="width:18px;height:18px;font-size:10px;" title="{{ $openSupport }} open support {{ \Illuminate\Support\Str::plural('ticket', $openSupport) }}">{{ $openSupport > 9 ? '9+' : $openSupport }}</span>@endif
						</a>
					</li>
					@endif
				</ul>
				<!--end::Tabs-->
			</div>
			<!--end::Nav-->
		</div>
		<!--end::Nav-->
		<!--begin::Footer-->
		@include('pages.business.include.side-menu.footer.footer')
		
		<!--end::Footer-->
	</div>
	<!--end::Primary-->
</div>
