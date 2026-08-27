<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — Budgetra</title>
    <link rel="icon" type="image/png" href="{{ asset('systemicons/budgetraicon-modified.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>
<body style="margin:0;padding:0;">

<div class="auth-wrapper">
    {{-- Left panel --}}
    <div class="auth-panel-left" style="position:relative;overflow:hidden;order:2;">
        <div style="position:absolute;inset:0;background-image:url('{{ asset('stockimages/loginsidebar.jpg') }}');background-size:cover;background-position:center;filter:blur(3px);transform:scale(1.05);"></div>
        <div style="position:absolute;inset:0;background:rgba(0,0,0,.25);"></div>
        <div style="position:relative;z-index:2;height:100%;display:flex;align-items:center;justify-content:center;">
            <img src="{{ asset('systemicons/budgetraicon-modified.png') }}" alt="Budgetra" style="max-width:200px;width:60%;">
        </div>
    </div>

    {{-- Right panel --}}
    <div class="auth-panel-right" style="order:1;">
        <div class="auth-form-wrap">
            <h1 class="auth-title">Welcome Back!</h1>
            <p class="auth-subtitle">Enter your credentials to access your trips.</p>

            @if ($errors->any())
            <div class="alert alert-danger">
                {{ $errors->first() }}
            </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input id="email" type="email" name="email"
                           value="{{ old('email') }}"
                           class="form-control {{ $errors->has('email') ? 'is-invalid' : '' }}"
                           placeholder="name@example.com" required autofocus>
                    @error('email')<div class="error">{{ $message }}</div>@enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-wrapper">
                        <span class="input-icon"><i class="fa-solid fa-key"></i></span>
                        <input id="password" type="password" name="password"
                               class="form-control {{ $errors->has('password') ? 'is-invalid' : '' }}"
                               placeholder="••••••••" required>
                        <span class="input-suffix" id="togglePwd" style="cursor:pointer;">
                            <i class="fa-regular fa-eye" id="eyeIcon"></i>
                        </span>
                    </div>
                    @error('password')<div class="error">{{ $message }}</div>@enderror
                </div>

                <button type="submit" class="btn btn-primary btn-lg btn-block">
                    Sign In &nbsp;→
                </button>
            </form>

            <div class="auth-footer-text">
                Don't have an account? <a href="{{ route('register') }}">Create one</a>
            </div>

            <div style="text-align:center;margin-top:32px;font-size:12px;color:var(--muted);">
                © {{ date('Y') }} Budgetra. Smart travel, smarter spending.
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('togglePwd').addEventListener('click', function () {
    var pwd  = document.getElementById('password');
    var icon = document.getElementById('eyeIcon');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.className = 'fa-regular fa-eye-slash';
    } else {
        pwd.type = 'password';
        icon.className = 'fa-regular fa-eye';
    }
});
</script>

</body>
</html>
