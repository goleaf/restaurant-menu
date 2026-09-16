@props(['users'])

<section class="rm-login-directory" aria-labelledby="local-users-title">
    <flux:heading level="2" size="lg" id="local-users-title">{{ __('local_login.title') }}</flux:heading>
    <flux:text>{{ __('local_login.description') }}</flux:text>

    <table class="rm-login-directory__table">
        <caption class="sr-only">{{ __('local_login.title') }}</caption>
        <thead>
            <tr>
                <th scope="col">{{ __('local_login.identity') }}</th>
                <th scope="col">{{ __('ui.auth.confirm_password.password') }}</th>
                <th scope="col">{{ __('staff.role') }}</th>
                <th scope="col">{{ __('local_login.companies') }}</th>
                <th scope="col">{{ __('local_login.permissions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($users as $user)
                <tr>
                    <td data-label="{{ __('local_login.identity') }}">
                        <form method="POST" action="{{ route('local-login.authenticate', ['user' => $user['id']]) }}">
                            @csrf
                            <flux:button type="submit" variant="filled" class="h-auto! min-h-touch w-full whitespace-normal! justify-start text-start" :aria-label="__('local_login.sign_in_as', ['name' => $user['name']])" data-test="local-login-user-{{ $user['id'] }}">
                                {{ $user['name'] }}
                            </flux:button>
                        </form>
                        <span>{{ $user['email'] }}</span>
                    </td>
                    <td data-label="{{ __('ui.auth.confirm_password.password') }}">
                        @if ($user['password'] !== null)
                            <code>{{ $user['password'] }}</code>
                        @else
                            <span>{{ __('local_login.password_unknown') }}</span>
                        @endif
                    </td>
                    <td data-label="{{ __('staff.role') }}">
                        @forelse ($user['roles'] as $role)
                            <span>{{ $role }}</span>
                        @empty
                            <span>{{ __('local_login.none') }}</span>
                        @endforelse
                    </td>
                    <td data-label="{{ __('local_login.companies') }}">
                        @forelse ($user['companies'] as $company)
                            <span>{{ $company }}</span>
                        @empty
                            <span>{{ __('local_login.none') }}</span>
                        @endforelse
                    </td>
                    <td data-label="{{ __('local_login.permissions') }}">
                        <details>
                            <summary>{{ __('local_login.show_permissions', ['name' => $user['name']]) }}</summary>
                            <p>{{ __('staff.workspace.role_defaults_help') }}</p>
                            @forelse ($user['grants'] as $grant)
                                <strong>{{ $grant['scope'] }}</strong>
                                <ul>
                                    @forelse ($grant['permissions'] as $permission)
                                        <li>{{ $permission }}</li>
                                    @empty
                                        <li>{{ __('local_login.none') }}</li>
                                    @endforelse
                                </ul>
                            @empty
                                <p>{{ __('local_login.none') }}</p>
                            @endforelse
                            <strong>{{ __('local_login.overrides') }}</strong>
                            <ul>
                                @forelse ($user['overrides'] as $override)
                                    <li>
                                        {{ $override['scope'] }} · {{ $override['permission'] }} ·
                                        {{ $override['enabled'] ? __('local_login.allowed') : __('local_login.denied') }}
                                    </li>
                                @empty
                                    <li>{{ __('local_login.none') }}</li>
                                @endforelse
                            </ul>
                        </details>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">{{ __('local_login.empty') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    {{ $users->links() }}
</section>
