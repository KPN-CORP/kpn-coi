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
Login to Commitment Corner
</x-mail::button>

### Security Notice

If you did not request this password reset or experience any issues accessing the system,
Please contact your HCO Team

Thanks,<br>
HC Teams

</x-mail::message>