<div class="metismenu pb-10 pt-2.5" id="sidebar-menu">
    <ul id="side-menu">
        <x-menu.sidebar-nav>
            <x-menu.sidebar-nav-link
                :href="route('admin.dashboard')"
                icon="home"
                :active="request()->routeIs('admin.dashboard', 'admin.index')"
            >
                Dashboard
            </x-menu.sidebar-nav-link>
        </x-menu.sidebar-nav>

        @canany(['settings.manage', 'employees.view'])
            <x-menu.sidebar-nav label="System Administration">
                @can('settings.manage')
                    <x-menu.sidebar-nav-link
                        :href="route('admin.config')"
                        icon="settings"
                        :active="request()->routeIs('admin.config')"
                    >
                        Einstellungen
                    </x-menu.sidebar-nav-link>
                @endcan

                @can('employees.view')
                    <x-menu.sidebar-nav-link
                        :href="route('admin.employees')"
                        icon="users"
                        :active="request()->routeIs('admin.employees')"
                    >
                        Mitarbeiter
                    </x-menu.sidebar-nav-link>
                @endcan

            </x-menu.sidebar-nav>
        @endcanany

        @can('ratings.view')
            <x-menu.sidebar-nav label="Management">
                <x-menu.sidebar-nav-link
                    :href="route('admin.reviews')"
                    icon="star"
                    :active="request()->routeIs('admin.reviews')"
                >
                    Bewertungen
                </x-menu.sidebar-nav-link>
            </x-menu.sidebar-nav>
        @endcan
    </ul>
</div>
