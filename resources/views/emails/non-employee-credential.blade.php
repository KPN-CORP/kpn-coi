<x-mail::message>

# Welcome to {{ $appName }}

Dear **{{ $user->name }}**,

Your account has been successfully created.

## Login Information

| | |
|:-|:-|
| Email | {{ $user->email }} |
| Password | **{{ $password }}** |

<x-mail::button :url="$appUrl">
Login to COI System
</x-mail::button>

### Security Notice

For security reasons, please change your password immediately after your first login.

If you experience any issues accessing the system, please contact:

**{{ $supportEmail }}**

Thanks,<br>
{{ $appName }}

</x-mail::message>