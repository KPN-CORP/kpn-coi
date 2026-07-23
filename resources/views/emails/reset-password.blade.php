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
Login to Commitment Corner
</x-mail::button>

### Security Notice

If you did not request this password reset or experience any issues accessing the system,
Please contact your HCO Team

Thanks,<br>
HC Teams

</x-mail::message>