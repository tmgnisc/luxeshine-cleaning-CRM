@component('mail::message')
# New Contact Enquiry

You have received a new message from the LuxeShine Cleaning website contact form.

- **Name:** {{ $name }}
- **Email:** {{ $email }}
@if($phone)
- **Phone:** {{ $phone }}
@endif
@if($enquirySubject)
- **Subject:** {{ $enquirySubject }}
@endif

**Message:**

{{ $messageBody }}

@endcomponent
