
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - RailSafe</title>

    <style>
        :root {
            --primary: #0865F5;
            --primary-dark: #0752D4;
            --light-blue: #EAF2FF;
            --background: #F4F7FD;
            --text: #172B4D;
            --muted: #8492A6;
            --border: #DFE7F3;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Arial, sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px;
            background:
                radial-gradient(circle at 10% 15%,
                    rgba(255,255,255,.15), transparent 22%),
                radial-gradient(circle at 90% 85%,
                    rgba(255,255,255,.12), transparent 25%),
                linear-gradient(135deg, #0865F5, #0752D4);
            position: relative;
            overflow: hidden;
        }

        /* Ornamen background */
        body::before,
        body::after {
            content: "";
            position: absolute;
            background: rgba(255,255,255,.06);
            transform: rotate(-35deg);
            pointer-events: none;
        }

        body::before {
            width: 350px;
            height: 900px;
            top: -350px;
            left: 15%;
        }

        body::after {
            width: 250px;
            height: 800px;
            bottom: -350px;
            right: 12%;
        }

        .login-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 1050px;
            min-height: 610px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: #fff;
            border-radius: 22px;
            overflow: hidden;
            box-shadow: 0 25px 70px rgba(0, 35, 100, .25);
        }

        /* Bagian kiri */
        .login-info {
            background: linear-gradient(145deg, #0865F5, #327FF9);
            color: white;
            padding: 55px 45px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 23px;
            font-weight: 700;
            letter-spacing: .5px;
        }

        .brand-icon {
            width: 43px;
            height: 43px;
            background: white;
            color: var(--primary);
            border-radius: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 23px;
        }

        .info-content {
            position: relative;
            z-index: 1;
        }

        .info-content h1 {
            font-size: 36px;
            line-height: 1.3;
            margin-bottom: 18px;
        }

        .info-content p {
            color: #DCE9FF;
            font-size: 15px;
            line-height: 1.8;
            max-width: 370px;
        }

        .system-status {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 12px;
            color: #E4EDFF;
        }

        .status-dot {
            width: 9px;
            height: 9px;
            background: #4ADE80;
            border-radius: 50%;
        }

        .decoration {
            position: absolute;
            width: 270px;
            height: 270px;
            border: 1px solid rgba(255,255,255,.13);
            border-radius: 50%;
            right: -100px;
            bottom: 70px;
        }

        .decoration::after {
            content: "";
            position: absolute;
            inset: 25px;
            border: 1px solid rgba(255,255,255,.13);
            border-radius: 50%;
        }

        /* Bagian kanan */
        .login-form-section {
            padding: 65px 55px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-heading h2 {
            font-size: 29px;
            color: var(--text);
            margin-bottom: 10px;
        }

        .form-heading p {
            color: var(--muted);
            font-size: 14px;
            margin-bottom: 35px;
        }

        .form-group {
            margin-bottom: 22px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #344563;
            margin-bottom: 9px;
        }

        .form-group input {
            width: 100%;
            padding: 14px 15px;
            border: 1px solid var(--border);
            border-radius: 9px;
            outline: none;
            font-size: 14px;
            color: var(--text);
            background: #FAFCFF;
            transition: .2s;
        }

        .form-group input:focus {
            border-color: var(--primary);
            background: white;
            box-shadow: 0 0 0 3px rgba(8,101,245,.10);
        }

        .form-options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 5px 0 25px;
            font-size: 13px;
            color: #64748B;
        }

        .remember {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .remember input {
            accent-color: var(--primary);
        }

        .login-button {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 9px;
            background: var(--primary);
            color: white;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: .2s;
        }

        .login-button:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 7px 18px rgba(8,101,245,.2);
        }

        .error-message {
            background: #FFF0F0;
            border: 1px solid #FFD5D5;
            color: #D92D20;
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
        }

        .form-footer {
            text-align: center;
            margin-top: 35px;
            color: var(--muted);
            font-size: 12px;
        }

        @media (max-width: 768px) {
            body {
                padding: 18px;
            }

            .login-container {
                grid-template-columns: 1fr;
                max-width: 450px;
                min-height: auto;
            }

            .login-info {
                padding: 28px;
                min-height: 190px;
            }

            .info-content {
                margin-top: 30px;
            }

            .info-content h1 {
                font-size: 24px;
            }

            .info-content p {
                font-size: 13px;
            }

            .system-status {
                display: none;
            }

            .login-form-section {
                padding: 35px 28px;
            }

            .form-heading h2 {
                font-size: 25px;
            }
        }
    </style>
</head>

<body>

    <div class="login-container">

        <div class="login-info">
            <div class="brand">
                <div class="brand-icon">↗</div>
                RAILSAFE
            </div>

            <div class="info-content">
                <h1>Sistem Monitoring<br>Keselamatan Perlintasan</h1>
                <p>
                    Pantau kondisi perangkat, deteksi kereta,
                    dan status palang perlintasan secara
                    terintegrasi melalui satu dashboard.
                </p>
            </div>

            <div class="system-status">
                <span class="status-dot"></span>
                Integrated Railway Monitoring System
            </div>

            <div class="decoration"></div>
        </div>

        <div class="login-form-section">

            <div class="form-heading">
                <h2>Selamat Datang!</h2>
                <p>Silakan login untuk mengakses dashboard RailSafe.</p>
            </div>

            @if ($errors->any())
                <div class="error-message">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.store') }}">
                @csrf

                <div class="form-group">
                    <label for="email">Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email') }}"
                        placeholder="Masukkan email Anda"
                        required
                        autofocus
                    >
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Masukkan password Anda"
                        required
                    >
                </div>

                <div class="form-options">
                    <label class="remember">
                        <input type="checkbox" name="remember" value="1">
                        Ingat saya
                    </label>
                </div>

                <button type="submit" class="login-button">
                    Masuk ke Dashboard
                </button>
            </form>

            <div class="form-footer">
                &copy; {{ date('Y') }} RailSafe Monitoring System
            </div>

        </div>
    </div>

</body>
</html>
