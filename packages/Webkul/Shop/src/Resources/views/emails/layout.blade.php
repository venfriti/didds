<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />

        <link
            rel="preconnect"
            href="https://fonts.googleapis.com"
            crossorigin
        />

        <link
            rel="preconnect"
            href="https://fonts.gstatic.com"
            crossorigin
        />

        <link
            href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@400;500;600;700;800&family=DM+Serif+Display&display=swap"
            rel="stylesheet"
        />
    </head>

    <body style="font-family: 'Inter', Arial, sans-serif;">
        <div style="max-width: 640px; margin-left: auto; margin-right: auto;">
            <div style="padding: 30px;">
                <!-- Email Header -->
                <div style="margin-bottom: 65px;">
                    <a
                        href="{{ route('shop.home.index') }}"
                        style="font-family: 'Inter', Arial, sans-serif; font-size: 28px; font-weight: 900; letter-spacing: 0.25px; text-decoration: none; color: #0A0A0A;"
                    >DIIDS</a>
                </div>

                <!-- Email Content -->
                {{ $slot }}

                <!-- Email Footer -->
                <p style="font-size: 16px;color: #0A0A0A;line-height: 24px;">
                    @lang('shop::app.emails.thanks', [
                        'link' => 'mailto:' . core()->getContactEmailDetails()['email'],
                        'email' => core()->getContactEmailDetails()['email'],
                        'style' => 'color: #0A0A0A; text-decoration: underline;'
                    ])
                </p>
            </div>
        </div>
    </body>
</html>
