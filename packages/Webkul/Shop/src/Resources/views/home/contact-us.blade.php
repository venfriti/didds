<!-- Page Layout -->
<x-shop::layouts>
    <!-- Page Title -->
    <x-slot:title>
        @lang('shop::app.home.contact.title')
    </x-slot>

    <section class="diids-contact">
        <!-- Left: brand-side info -->
        <aside class="diids-contact__aside">
            <p class="diids-contact__kicker">DIIDS / Contact</p>
            <h1 class="diids-contact__title">Let's talk</h1>
            <p class="diids-contact__lead">
                Questions about fit, an order, or a wholesale enquiry? Send a note
                and the team will get back to you.
            </p>

            <dl class="diids-contact__details">
                <div>
                    <dt>Email</dt>
                    <dd><a href="mailto:{{ core()->getConfigData('emails.configure.email_settings.contact_email') ?: 'shop@mail.diids.com' }}">{{ core()->getConfigData('emails.configure.email_settings.contact_email') ?: 'shop@mail.diids.com' }}</a></dd>
                </div>
                <div>
                    <dt>Support</dt>
                    <dd>By chat &amp; email, every day</dd>
                </div>
                <div>
                    <dt>Follow</dt>
                    <dd>@diids</dd>
                </div>
            </dl>
        </aside>

        <!-- Right: form -->
        <div class="diids-contact__panel">
            <div
                id="contact-us-form"
                class="diids-contact__form"
            >
                <!-- Contact Form -->
                <x-shop::form :action="route('shop.home.contact_us.send_mail')">
                    <!-- Name -->
                    <x-shop::form.control-group>
                        <x-shop::form.control-group.label class="required">
                            @lang('shop::app.home.contact.name')
                        </x-shop::form.control-group.label>

                        <x-shop::form.control-group.control
                            type="text"
                            class="px-6 py-5 max-md:py-3 max-sm:py-3.5"
                            name="name"
                            rules="required"
                            :value="old('name')"
                            :label="trans('shop::app.home.contact.name')"
                            :placeholder="trans('shop::app.home.contact.name')"
                            :aria-label="trans('shop::app.home.contact.name')"
                            aria-required="true"
                        />

                        <x-shop::form.control-group.error control-name="name" />
                    </x-shop::form.control-group>

                    <!-- Email -->
                    <x-shop::form.control-group>
                        <x-shop::form.control-group.label class="required">
                            @lang('shop::app.home.contact.email')
                        </x-shop::form.control-group.label>

                        <x-shop::form.control-group.control
                            type="email"
                            class="px-6 py-5 max-md:py-3 max-sm:py-3.5"
                            name="email"
                            rules="required|email"
                            :value="old('email')"
                            :label="trans('shop::app.home.contact.email')"
                            :placeholder="trans('shop::app.home.contact.email')"
                            :aria-label="trans('shop::app.home.contact.email')"
                            aria-required="true"
                        />

                        <x-shop::form.control-group.error control-name="email" />
                    </x-shop::form.control-group>

                    <!-- Contact -->
                    <x-shop::form.control-group>
                        <x-shop::form.control-group.label>
                            @lang('shop::app.home.contact.phone-number')
                        </x-shop::form.control-group.label>

                        <x-shop::form.control-group.control
                            type="text"
                            class="px-6 py-5 max-md:py-3 max-sm:py-3.5"
                            name="contact"
                            rules="phone"
                            :value="old('contact')"
                            :label="trans('shop::app.home.contact.phone-number')"
                            :placeholder="trans('shop::app.home.contact.phone-number')"
                            :aria-label="trans('shop::app.home.contact.phone-number')"
                        />

                        <x-shop::form.control-group.error control-name="contact" />
                    </x-shop::form.control-group>

                    <!-- Message -->
                    <x-shop::form.control-group>
                        <x-shop::form.control-group.label class="required">
                            @lang('shop::app.home.contact.desc')
                        </x-shop::form.control-group.label>

                        <x-shop::form.control-group.control
                            type="textarea"
                            class="px-6 py-5 max-md:py-3 max-sm:py-3.5"
                            name="message"
                            rules="required"
                            :label="trans('shop::app.home.contact.message')"
                            :placeholder="trans('shop::app.home.contact.describe-here')"
                            :aria-label="trans('shop::app.home.contact.message')"
                            aria-required="true"
                            rows="10"
                        />

                        <x-shop::form.control-group.error control-name="message" />
                    </x-shop::form.control-group>

                    <!-- Captcha -->
                    @if (core()->getConfigData('customer.captcha.credentials.status'))
                        <x-shop::form.control-group class="mt-5">
                            {!! \Webkul\Customer\Facades\Captcha::render() !!}

                            <x-shop::form.control-group.error control-name="recaptcha_token" />
                        </x-shop::form.control-group>
                    @endif

                    <!-- Submit Button -->
                    <button
                        class="diids-btn diids-btn--solid mt-6 !w-full justify-center"
                        type="submit"
                    >
                        <span>@lang('shop::app.home.contact.submit')</span>
                    </button>
                </x-shop::form>
            </div>
        </div>
    </section>

    @push('scripts')
        {!! \Webkul\Customer\Facades\Captcha::renderJS() !!}
    @endpush
</x-shop::layouts>
