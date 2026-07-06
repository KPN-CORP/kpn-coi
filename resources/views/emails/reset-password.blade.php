<x-mail::message>

# Password Reset

Dear **{{ $user->name }}**,

Your password has been successfully reset by the system administrator.

## Login Information

| | |
|:-|:-|
| Email | {{ $user->email }} |
| New Password | **{{ $password }}** |

<x-mail::button :url="$appUrl">
Login to Compliance System
</x-mail::button>

### Security Notice

For security reasons, please change your password immediately after logging in using this temporary password.

If you did not request this password reset or experience any issues accessing the system, please contact:

**{{ $supportEmail }}**

Thanks,<br>
{{ $appName }}

</x-mail::message>