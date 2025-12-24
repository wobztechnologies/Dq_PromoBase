<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'PromoBase') }}</title>
        <link rel="icon" type="image/png" href="{{ asset('img/pormobase.png') }}">
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            html, body {
                height: 100%;
                width: 100%;
            }
            
            body {
                background-color: #000000;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
            }
            
            .logo-container {
                display: flex;
                align-items: center;
                justify-content: center;
                animation: fadeIn 1s ease-out;
            }
            
            .logo {
                max-width: 400px;
                width: 80vw;
                height: auto;
            }
            
            @keyframes fadeIn {
                from {
                    opacity: 0;
                    transform: scale(0.95);
                }
                to {
                    opacity: 1;
                    transform: scale(1);
                }
            }
            </style>
    </head>
    <body>
        <div class="logo-container">
            <img src="{{ asset('img/pormobase.png') }}" alt="PromoBase" class="logo">
        </div>
    </body>
</html>
