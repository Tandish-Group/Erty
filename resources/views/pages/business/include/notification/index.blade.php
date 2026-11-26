{{-- Notifications drawer: filled by public/js/pages/business/notifications.js from /notifications. --}}
<div id="kt_drawer_chat" class="bg-body notif-drawer" data-kt-drawer="true" data-kt-drawer-name="chat" data-kt-drawer-activate="true" data-kt-drawer-overlay="true" data-kt-drawer-width="{default:'320px', 'md': '440px'}" data-kt-drawer-direction="end" data-kt-drawer-toggle="#kt_drawer_chat_toggle" data-kt-drawer-close="#kt_drawer_chat_close" aria-label="Notifications">
    <div class="notif-panel">
        <header class="notif-head">
            <div>
                <h2>Notifications</h2>
                <span data-notif-unread-label>{{ ($unreadCount = auth()->user()->unreadNotifications()->count()) ? $unreadCount . ' unread' : 'All caught up' }}</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-light" data-notif-read-all @disabled(!$unreadCount)>Mark all as read</button>
                <button type="button" class="btn btn-sm btn-icon btn-light" id="kt_drawer_chat_close" aria-label="Close notifications"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
            </div>
        </header>
        <nav class="notif-tabs" role="tablist" aria-label="Show">
            <button type="button" class="active" role="tab" aria-selected="true" data-notif-filter="all">All</button>
            <button type="button" role="tab" aria-selected="false" data-notif-filter="unread">Unread</button>
        </nav>
        <ul class="notif-list" data-notif-list aria-live="polite">
            <li class="notif-empty"><span>Loading…</span></li>
        </ul>
    </div>
</div>
