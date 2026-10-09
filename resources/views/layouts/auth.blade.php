<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">

<head>
    @include('partials.head')
    <style>
        .fit-auth-page {
            --fit-navy: #1b2a4a;
            position: relative;
            min-height: 100vh;
            overflow: hidden;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 24px;
        }

        .fit-auth-circle {
            position: absolute;
            width: 420px;
            height: 420px;
            border-radius: 9999px;
            background: var(--fit-navy);
            pointer-events: none;
        }

        .fit-auth-circle.top {
            top: -220px;
            right: -130px;
        }

        .fit-auth-circle.bottom {
            bottom: -220px;
            left: -130px;
        }

        .fit-auth-card {
            position: relative;
            z-index: 2;
            width: min(100%, 496px);
            border: 3px solid var(--fit-navy);
            background: #fff;
            padding: 0 16px 28px;
            box-shadow: 0 1px 0 rgba(27, 42, 74, .04);
        }

        .fit-auth-brand {
            margin-top: -7px;
            background: #fff;
            padding: 0 12px 4px;
            width: max-content;
            margin-left: auto;
            margin-right: auto;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 23px;
            line-height: 1;
            font-weight: 800;
            color: var(--fit-navy);
            letter-spacing: -.04em;
        }

        .fit-auth-tabs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            margin-top: 15px;
            border-bottom: 1px solid #e7e7e7;
        }

        .fit-auth-tab {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 35px;
            border-bottom: 2px solid transparent;
            color: #c8c8c8;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            text-decoration: none;
        }

        .fit-auth-tab.active {
            border-color: var(--fit-navy);
            color: var(--fit-navy);
        }

        .fit-auth-form {
            padding-top: 21px;
        }

        .fit-auth-field {
            margin-bottom: 16px;
        }

        .fit-auth-field label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 7px;
            color: var(--fit-navy);
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: .1em;
            text-transform: uppercase;
        }

        .fit-auth-field input {
            width: 100%;
            height: 34px;
            border: 1px solid #d8d8d8;
            border-radius: 0;
            background: #fff;
            padding: 0 10px;
            color: var(--fit-navy);
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            outline: none;
            box-shadow: none;
        }

        .fit-auth-field input:focus {
            border-color: var(--fit-navy);
            box-shadow: 0 0 0 1px var(--fit-navy);
        }

        .fit-auth-error {
            margin-top: 5px;
            color: #b42318;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9px;
        }

        .fit-auth-check {
            display: flex;
            align-items: center;
            gap: 7px;
            margin-top: 2px;
            color: #888;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9px;
        }

        .fit-auth-check input {
            width: 12px;
            height: 12px;
            accent-color: var(--fit-navy);
        }

        .fit-auth-submit {
            width: 100%;
            height: 35px;
            margin-top: 15px;
            border: 1px solid var(--fit-navy);
            border-radius: 0;
            background: var(--fit-navy);
            color: #fff;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .13em;
            text-transform: uppercase;
            cursor: pointer;
            transition: background .15s ease, transform .15s ease;
        }

        .fit-auth-submit:hover {
            background: #111f3a;
        }

        .fit-auth-submit:active {
            transform: translateY(1px);
        }

        .fit-auth-divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 24px 0 14px;
            color: #d0d0d0;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 7px;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .fit-auth-divider::before,
        .fit-auth-divider::after {
            content: '';
            height: 1px;
            flex: 1;
            background: #e7e7e7;
        }

        .fit-auth-guest {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 35px;
            border: 1px solid var(--fit-navy);
            background: #fff;
            color: var(--fit-navy);
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: .11em;
            text-transform: uppercase;
            text-decoration: none;
            transition: background .15s ease, color .15s ease;
        }

        .fit-auth-guest:hover {
            background: var(--fit-navy);
            color: #fff;
        }

        .fit-auth-footnote {
            position: absolute;
            bottom: 13px;
            left: 0;
            right: 0;
            z-index: 2;
            text-align: center;
            color: rgba(27, 42, 74, .5);
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8px;
            pointer-events: none;
        }

        @media (max-width: 640px) {
            .fit-auth-page {
                padding: 28px 14px;
            }

            .fit-auth-circle {
                width: 280px;
                height: 280px;
            }

            .fit-auth-circle.top {
                top: -155px;
                right: -105px;
            }

            .fit-auth-circle.bottom {
                bottom: -155px;
                left: -105px;
            }

            .fit-auth-card {
                width: 100%;
                padding-left: 13px;
                padding-right: 13px;
            }
        }
    </style>
</head>

<body class="min-h-full antialiased">
    <main class="fit-auth-page">
        <div class="fit-auth-circle top"></div>
        <div class="fit-auth-circle bottom"></div>

        {{ $slot }}

        <p class="fit-auth-footnote">&copy; {{ date('Y') }} FITMATE</p>
    </main>

    @livewireScripts
</body>

</html>