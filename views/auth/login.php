<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ —
        <?= APP_NAME ?>
    </title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Prompt', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0c4a1f 0%, #1a6b2f 30%, #2d8a4e 60%, #166534 100%);
            position: relative;
            overflow: hidden;
        }

        /* Decorative background elements */
        body::before {
            content: '';
            position: absolute;
            width: 600px;
            height: 600px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(34, 197, 94, 0.15) 0%, transparent 70%);
            top: -200px;
            right: -200px;
        }

        body::after {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.1) 0%, transparent 70%);
            bottom: -150px;
            left: -150px;
        }

        .login-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 440px;
            padding: 20px;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 48px 40px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.3),
                0 0 0 1px rgba(255, 255, 255, 0.1);
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-logo {
            text-align: center;
            margin-bottom: 32px;
        }

        .login-logo .icon-circle {
            width: 80px;
            height: 80px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
        }

        .login-logo h1 {
            font-size: 22px;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 4px;
        }

        .login-logo p {
            font-size: 14px;
            color: #6b7280;
            font-weight: 300;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #374151;
            margin-bottom: 8px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 18px;
            color: #9ca3af;
            transition: color 0.3s;
        }

        .input-wrapper input {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 2px solid #e5e7eb;
            border-radius: 14px;
            font-family: 'Prompt', sans-serif;
            font-size: 15px;
            color: #1f2937;
            background: #f9fafb;
            transition: all 0.3s;
            outline: none;
        }

        .input-wrapper input:focus {
            border-color: #16a34a;
            background: white;
            box-shadow: 0 0 0 4px rgba(22, 163, 74, 0.1);
        }

        .input-wrapper input:focus+i,
        .input-wrapper input:focus~i {
            color: #16a34a;
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 14px;
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: white;
            font-family: 'Prompt', sans-serif;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 8px;
            box-shadow: 0 4px 16px rgba(22, 163, 74, 0.3);
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(22, 163, 74, 0.4);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 14px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            animation: shake 0.4s ease;
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            25% {
                transform: translateX(-5px);
            }

            75% {
                transform: translateX(5px);
            }
        }

        .footer-text {
            text-align: center;
            margin-top: 24px;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.5);
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-logo">
                <div class="icon-circle" style="background:none; width:80px; height:80px; display:flex; align-items:center; justify-content:center;">
                    <svg viewBox="0 0 100 100" width="64" height="64" xmlns="http://www.w3.org/2000/svg">
                        <path d="M50 5 L90 20 L90 55 Q90 80 50 95 Q10 80 10 55 L10 20 Z" fill="#166534" stroke="#a7f3d0" stroke-width="2.5"/>
                        <path d="M50 12 L83 24 L83 53 Q83 74 50 88 Q17 74 17 53 L17 24 Z" fill="none" stroke="#a7f3d0" stroke-width="1" opacity="0.5"/>
                        <rect x="28" y="35" width="18" height="14" rx="1" fill="#4ade80" opacity="0.9"/>
                        <rect x="49" y="35" width="22" height="14" rx="1" fill="#22c55e" opacity="0.8"/>
                        <rect x="28" y="52" width="12" height="16" rx="1" fill="#22c55e" opacity="0.8"/>
                        <rect x="43" y="52" width="16" height="16" rx="1" fill="#86efac" opacity="0.7"/>
                        <rect x="62" y="52" width="9" height="16" rx="1" fill="#4ade80" opacity="0.9"/>
                        <polygon points="50,18 42,32 58,32" fill="#bbf7d0"/>
                        <polygon points="50,23 44,34 56,34" fill="#86efac"/>
                        <rect x="48" y="32" width="4" height="4" fill="#a7f3d0"/>
                    </svg>
                </div>
                <h1>
                    <?= APP_NAME ?>
                </h1>
                <p>
                    <?= APP_SUBTITLE ?>
                </p>
            </div>

            <?php if (isset($_SESSION['login_error'])): ?>
                <div class="alert-error">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <?= htmlspecialchars($_SESSION['login_error']) ?>
                </div>
                <?php unset($_SESSION['login_error']); ?>
            <?php endif; ?>

            <form method="POST" action="index.php?page=login">
                <div class="form-group">
                    <label for="username">ชื่อผู้ใช้</label>
                    <div class="input-wrapper">
                        <input type="text" id="username" name="username" placeholder="กรอก Username" required
                            autocomplete="username">
                        <i class="bi bi-person-fill"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">รหัสผ่าน</label>
                    <div class="input-wrapper">
                        <input type="password" id="password" name="password" placeholder="กรอก Password" required
                            autocomplete="current-password">
                        <i class="bi bi-lock-fill"></i>
                    </div>
                </div>

                <button type="submit" class="btn-login">
                    <i class="bi bi-box-arrow-in-right"></i> เข้าสู่ระบบ
                </button>
            </form>
        </div>
        <p class="footer-text">
            <?= APP_NAME ?> v
            <?= APP_VERSION ?>
        </p>
    </div>
</body>

</html>