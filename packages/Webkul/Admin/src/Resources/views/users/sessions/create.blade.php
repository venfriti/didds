<x-admin::layouts.anonymous>
    <!-- Page Title -->
    <x-slot:title>
        @lang('admin::app.users.sessions.title')
    </x-slot>

    <div class="diids-admin-auth">
        <!-- Left: editorial image -->
        <div class="diids-admin-auth__visual">
            <img src="{{ bagisto_asset('images/auth-visual.webp') }}" alt="DIIDS">
            <p class="diids-admin-auth__tagline">Everyday Confidence</p>
        </div>

        <!-- Right: form -->
        <div class="diids-admin-auth__panel-wrap">
            <div class="diids-admin-auth__inner">
                <!-- Logo -->
                <img
                    class="diids-admin-auth__logo"
                    src="{{ bagisto_asset('images/diids-logo.svg') }}"
                    alt="{{ config('app.name') }}"
                />

                <p class="diids-admin-auth__eyebrow">Admin</p>

                <div class="diids-admin-auth__panel">
                    <!-- Login Form -->
                    <x-admin::form :action="route('admin.session.store')">
                        <p class="diids-admin-auth__title">
                            @lang('admin::app.users.sessions.title')
                        </p>

                        <div class="diids-admin-auth__fields">
                            <!-- Email -->
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label class="required">
                                    @lang('admin::app.users.sessions.email')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="email"
                                    class="diids-admin-auth__input"
                                    id="email"
                                    name="email"
                                    rules="required|email"
                                    :label="trans('admin::app.users.sessions.email')"
                                    :placeholder="trans('admin::app.users.sessions.email')"
                                />

                                <x-admin::form.control-group.error control-name="email" />
                            </x-admin::form.control-group>

                            <!-- Password -->
                            <x-admin::form.control-group class="relative w-full">
                                <x-admin::form.control-group.label class="required">
                                    @lang('admin::app.users.sessions.password')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="password"
                                    class="diids-admin-auth__input ltr:pr-10 rtl:pl-10"
                                    id="password"
                                    name="password"
                                    rules="required|min:6"
                                    :label="trans('admin::app.users.sessions.password')"
                                    :placeholder="trans('admin::app.users.sessions.password')"
                                />

                                <span
                                    class="icon-view absolute top-[38px] -translate-y-2/4 cursor-pointer text-2xl ltr:right-2 rtl:left-2"
                                    onclick="switchVisibility()"
                                    id="visibilityIcon"
                                    role="presentation"
                                    tabindex="0"
                                >
                                </span>

                                <x-admin::form.control-group.error control-name="password" />
                            </x-admin::form.control-group>
                        </div>

                        <div class="diids-admin-auth__footer">
                            <!-- Forgot Password Link -->
                            <a
                                class="diids-admin-auth__forgot"
                                href="{{ route('admin.forget_password.create') }}"
                            >
                                @lang('admin::app.users.sessions.forget-password-link')
                            </a>

                            <!-- Submit Button -->
                            <button
                                class="diids-btn diids-btn--solid"
                                aria-label="{{ trans('admin::app.users.sessions.submit-btn')}}"
                            >
                                <span>@lang('admin::app.users.sessions.submit-btn')</span>
                            </button>
                        </div>
                    </x-admin::form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function switchVisibility() {
                let passwordField = document.getElementById("password");
                let visibilityIcon = document.getElementById("visibilityIcon");

                passwordField.type = passwordField.type === "password" ? "text" : "password";
                visibilityIcon.classList.toggle("icon-view-close");
            }
        </script>
    @endpush
</x-admin::layouts.anonymous>
