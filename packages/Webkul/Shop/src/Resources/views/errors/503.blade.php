{{--
    Maintenance page, shown to shoppers while the channel's maintenance
    switch is on (Admin > Settings > Channels > Maintenance Mode).

    Deliberately standalone rather than built on the shop layout: the layout
    boots Vue and calls the cart and customer APIs, which are themselves in
    maintenance and would only fail. This page needs nothing but its own
    fonts and one photograph.
--}}
@php
    $channel = core()->getCurrentChannel();

    $message = trim((string) ($channel?->maintenance_mode_text ?? ''));

    $contactEmail = core()->getConfigData('emails.configure.email_settings.contact_email') ?: 'info@diids.co';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>DIIDS | Back Soon</title>

    <link rel="icon" type="image/png" href="{{ bagisto_asset('images/diids-favicon.png') }}">
    <link rel="preload" as="image" href="{{ bagisto_asset('images/shoot/couple-a.webp') }}">

    <style>
        @font-face {
            font-family: "Magste";
            src: url("{{ bagisto_asset('fonts/Magste-Regular.woff2') }}") format("woff2");
            font-display: swap;
        }

        @font-face {
            font-family: "Grift";
            src: url("{{ bagisto_asset('fonts/Grift-Regular.woff2') }}") format("woff2");
            font-weight: 400;
            font-display: swap;
        }

        @font-face {
            font-family: "Grift";
            src: url("{{ bagisto_asset('fonts/Grift-Medium.woff2') }}") format("woff2");
            font-weight: 500;
            font-display: swap;
        }

        :root {
            --ink: #0A0A0A;
            --paper: #FFFFFF;
            --muted: #6B6B6B;
            --line: #E4E4E4;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        html, body { height: 100%; }

        body {
            background: var(--paper);
            color: var(--ink);
            font-family: "Grift", -apple-system, "Helvetica Neue", sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        .page {
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr);
            min-height: 100vh;
            min-height: 100dvh;
        }

        .photo {
            position: relative;
            overflow: hidden;
            background: #D9D9D6;
        }

        .photo img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: 50% 18%;
            animation: settle 1.8s cubic-bezier(.2, .7, .2, 1) both;
        }


        .panel {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: clamp(1.5rem, 4vw, 3.5rem);
            gap: 3rem;
        }

        .brand img { height: 30px; width: auto; display: block; }

        .body {
            max-width: 30rem;
            animation: rise 1s .25s cubic-bezier(.2, .7, .2, 1) both;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: .65rem;
            font-size: .72rem;
            font-weight: 500;
            letter-spacing: .28em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .eyebrow__dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--ink);
            animation: pulse 2s ease-in-out infinite;
        }

        h1 {
            margin-top: 1.5rem;
            font-family: "Magste", "Times New Roman", serif;
            font-weight: 400;
            font-size: clamp(2.6rem, 5.4vw, 4.6rem);
            line-height: 1.02;
            letter-spacing: -.01em;
        }

        .message {
            margin-top: 1.5rem;
            font-size: 1.05rem;
            line-height: 1.65;
            color: #333;
        }

        .rule {
            margin: 2.25rem 0 1.5rem;
            height: 1px;
            background: var(--line);
        }

        .contact {
            font-size: .92rem;
            line-height: 1.6;
            color: var(--muted);
        }

        .contact a {
            color: var(--ink);
            text-decoration: none;
            border-bottom: 1px solid var(--ink);
            padding-bottom: 1px;
        }

        .contact a:hover { opacity: .7; }

        .foot {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            font-size: .72rem;
            letter-spacing: .22em;
            text-transform: uppercase;
            color: var(--muted);
        }

        @keyframes settle {
            from { transform: scale(1.06); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        @keyframes rise {
            from { transform: translateY(14px); opacity: 0; }
            to { transform: none; opacity: 1; }
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: .25; }
        }

        @media (max-width: 860px) {
            .page { grid-template-columns: 1fr; }

            .photo { height: 58vh; min-height: 340px; }

            .photo img { object-position: 50% 12%; }

            .panel { padding: 1.75rem 1.25rem 1.5rem; gap: 2.25rem; }

            .brand { order: -1; }

            .foot { flex-direction: column; gap: .4rem; }
        }

        @media (prefers-reduced-motion: reduce) {
            .photo img, .body, .eyebrow__dot { animation: none; }
        }
    </style>
</head>
<body>
    <main class="page">
        <div class="photo">
            <img
                src="{{ bagisto_asset('images/shoot/couple-a.webp') }}"
                alt="Two models wearing DIIDS white underwear with light denim"
                width="1600"
                height="2073"
            >
        </div>

        <section class="panel">
            <div class="brand">
                <img src="{{ bagisto_asset('images/diids-logo.svg') }}" alt="DIIDS" width="92" height="30">
            </div>

            <div class="body">
                <span class="eyebrow">
                    <span class="eyebrow__dot" aria-hidden="true"></span>
                    Store update in progress
                </span>

                <h1>We'll be right back.</h1>

                <p class="message">
                    {{ $message !== '' ? $message : "The DIIDS store is closed for a short while as we make some improvements. Everything will be back shortly, thank you for your patience." }}
                </p>

                <div class="rule"></div>

                <p class="contact">
                    Need help with an order? Write to us at
                    <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>
                </p>
            </div>

            <div class="foot">
                <span>Everyday Confidence</span>
                <span>&copy; {{ date('Y') }} DIIDS</span>
            </div>
        </section>
    </main>
</body>
</html>
