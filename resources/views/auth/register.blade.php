<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account — Budgetra</title>
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
    <div class="auth-panel-left-register" style="position:relative;overflow:hidden;padding:0;">
        <div style="position:absolute;inset:0;background-image:url('{{ asset('stockimages/registersidebar.jpg') }}');background-size:cover;background-position:center;filter:blur(3px);transform:scale(1.05);"></div>
        <div style="position:absolute;inset:0;background:rgba(0,0,0,.25);"></div>
        <div style="position:relative;z-index:2;height:100%;display:flex;align-items:center;justify-content:center;">
            <img src="{{ asset('systemicons/budgetraicon-modified.png') }}" alt="Budgetra" style="max-width:200px;width:60%;">
        </div>
    </div>

    {{-- Right panel --}}
    <div class="auth-panel-right">
        <div class="auth-form-wrap">
            <h1 class="auth-title">Create an account</h1>
            <p class="auth-subtitle">Join thousands of smart travelers managing their money.</p>

            @if ($errors->any())
            <div class="alert alert-danger">
                {{ $errors->first() }}
            </div>
            @endif

            <form method="POST" action="{{ route('register') }}">
                @csrf

                {{-- First Name --}}
                <div class="form-group">
                    <label class="form-label" for="first_name">First Name</label>
                    <div class="input-wrapper">
                        <span class="input-icon"><i class="fa-regular fa-user"></i></span>
                        <input id="first_name" type="text" name="first_name"
                               value="{{ old('first_name') }}"
                               class="form-control {{ $errors->has('first_name') ? 'is-invalid' : '' }}"
                               placeholder="John" required autofocus>
                    </div>
                    @error('first_name')<div class="error">{{ $message }}</div>@enderror
                </div>

                {{-- Last Name --}}
                <div class="form-group">
                    <label class="form-label" for="last_name">Last Name</label>
                    <div class="input-wrapper">
                        <span class="input-icon"><i class="fa-regular fa-user"></i></span>
                        <input id="last_name" type="text" name="last_name"
                               value="{{ old('last_name') }}"
                               class="form-control {{ $errors->has('last_name') ? 'is-invalid' : '' }}"
                               placeholder="Doe" required>
                    </div>
                    @error('last_name')<div class="error">{{ $message }}</div>@enderror
                </div>

                {{-- Email --}}
                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <div class="input-wrapper">
                        <span class="input-icon"><i class="fa-regular fa-envelope"></i></span>
                        <input id="email" type="email" name="email"
                               value="{{ old('email') }}"
                               class="form-control {{ $errors->has('email') ? 'is-invalid' : '' }}"
                               placeholder="name@example.com" required>
                    </div>
                    @error('email')<div class="error">{{ $message }}</div>@enderror
                </div>

                {{-- Password --}}
                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-wrapper">
                        <span class="input-icon"><i class="fa-solid fa-lock"></i></span>
                        <input id="password" type="password" name="password"
                               class="form-control {{ $errors->has('password') ? 'is-invalid' : '' }}"
                               placeholder="••••••••" required>
                        <span class="input-suffix" id="togglePwd1" style="cursor:pointer;">
                            <i class="fa-regular fa-eye" id="eyeIcon1"></i>
                        </span>
                    </div>
                    @error('password')<div class="error">{{ $message }}</div>@enderror
                </div>

                {{-- Confirm Password --}}
                <div class="form-group">
                    <label class="form-label" for="password_confirmation">Confirm Password</label>
                    <div class="input-wrapper">
                        <span class="input-icon"><i class="fa-solid fa-lock-open"></i></span>
                        <input id="password_confirmation" type="password" name="password_confirmation"
                               class="form-control" placeholder="••••••••" required>
                        <span class="input-suffix" id="togglePwd2" style="cursor:pointer;">
                            <i class="fa-regular fa-eye" id="eyeIcon2"></i>
                        </span>
                    </div>
                </div>

                {{-- Country --}}
                <div class="form-group">
                    <label class="form-label" for="country">
                        <i class="fa-solid fa-globe" style="color:var(--primary);margin-right:4px;"></i>
                        Country
                    </label>
                    <div style="position:relative;">
                        <i class="fa-solid fa-earth-asia" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:14px;pointer-events:none;z-index:1;"></i>
                        <i class="fa-solid fa-chevron-down" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:11px;pointer-events:none;z-index:1;"></i>
                        <select name="country" id="country"
                                style="width:100%;padding:11px 34px 11px 36px;font-size:14px;font-family:inherit;
                                       border:1.5px solid var(--border);border-radius:var(--radius-sm);
                                       background:var(--bg-white);color:var(--text);outline:none;
                                       cursor:pointer;-webkit-appearance:none;appearance:none;
                                       transition:border-color 0.2s;"
                                onfocus="this.style.borderColor='var(--primary)';this.style.boxShadow='0 0 0 3px rgba(139,58,16,0.12)'"
                                onblur="this.style.borderColor='var(--border)';this.style.boxShadow='none'">
                            <option value="" disabled selected>Select your country</option>
                            @foreach([
                                'Philippines' => '🇵🇭', 'Indonesia' => '🇮🇩', 'Thailand' => '🇹🇭', 'Vietnam' => '🇻🇳',
                                'Malaysia' => '🇲🇾', 'Singapore' => '🇸🇬', 'Japan' => '🇯🇵', 'South Korea' => '🇰🇷',
                                'China' => '🇨🇳', 'India' => '🇮🇳', 'Australia' => '🇦🇺', 'New Zealand' => '🇳🇿',
                                'United States' => '🇺🇸', 'Canada' => '🇨🇦', 'United Kingdom' => '🇬🇧',
                                'Germany' => '🇩🇪', 'France' => '🇫🇷', 'Italy' => '🇮🇹', 'Spain' => '🇪🇸',
                                'Netherlands' => '🇳🇱', 'Brazil' => '🇧🇷', 'Mexico' => '🇲🇽', 'Argentina' => '🇦🇷',
                                'Saudi Arabia' => '🇸🇦', 'United Arab Emirates' => '🇦🇪', 'Egypt' => '🇪🇬',
                                'Nigeria' => '🇳🇬', 'South Africa' => '🇿🇦', 'Kenya' => '🇰🇪',
                            ] as $name => $flag)
                                <option value="{{ $name }}" {{ old('country') === $name ? 'selected' : '' }}>{{ $flag }} {{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Terms --}}
                <label class="auth-terms-check">
                    <input type="checkbox" id="agreeTerms">
                    <span>
                        By creating an account, I agree to the
                        <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>.
                    </span>
                </label>

                <button type="submit" id="createAccountBtn" class="btn btn-primary btn-block"
                        style="margin-bottom:16px;" disabled>
                    Create Account
                </button>
            </form>

            <div class="auth-footer-text">
                Already have an account? <a href="{{ route('login') }}">Login here</a>
            </div>

            <div style="text-align:center;margin-top:32px;font-size:12px;color:var(--muted);">
                © {{ date('Y') }} Budgetra. Smart travel, smarter spending.
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword(inputId, iconId) {
    var pwd  = document.getElementById(inputId);
    var icon = document.getElementById(iconId);
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.className = 'fa-regular fa-eye-slash';
    } else {
        pwd.type = 'password';
        icon.className = 'fa-regular fa-eye';
    }
}
document.getElementById('togglePwd1').addEventListener('click', function () {
    togglePassword('password', 'eyeIcon1');
});
document.getElementById('togglePwd2').addEventListener('click', function () {
    togglePassword('password_confirmation', 'eyeIcon2');
});
document.getElementById('agreeTerms').addEventListener('change', function () {
    document.getElementById('createAccountBtn').disabled = !this.checked;
});
</script>

</body>
</html>
