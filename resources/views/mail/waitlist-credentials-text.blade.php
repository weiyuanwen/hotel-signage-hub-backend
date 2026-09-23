SignageHub

Xin chào {{ $user->name }},

Quầy {{ $hotel->name }} đã mở.

Email: {{ $user->email }}
Mật khẩu tạm: {{ $plainPassword }}
Gói: {{ $planLabel }} ({{ $deviceLabel }})
@foreach ($featureLines as $line)
- {{ $line }}
@endforeach

Vào quầy: {{ $loginUrl }}

Đổi mật khẩu sau lần đăng nhập đầu. Nếu không phải bạn, bỏ qua thư này.
