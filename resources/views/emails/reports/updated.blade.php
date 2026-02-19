@component('mail::message')
<div style="text-align: center; margin-bottom: 30px;">
    <img src="{{ config('app.url') }}/logo-black.png" alt="{{ config('app.name') }}" style="height: 60px; width: auto;">
</div>

# {{ $customTitle }}

{{ __('Hello') }}, {{ $user->name }}

{{ $customIntro }}

<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0; margin: 25px 0;">
    <tr>
        <td style="padding: 20px;">
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td width="50%" align="left" style="padding-right: 10px;">
                        <p style="margin: 0; font-size: 11px; color: #64748b; font-weight: bold; text-transform: uppercase; letter-spacing: 0.05em;">{{ __('Update Date') }}</p>
                        <p style="margin: 0; font-size: 16px; color: #1e293b; font-weight: 800; margin-top: 4px;">{{ $updateDate }}</p>
                    </td>
                    <td width="50%" align="right" style="padding-left: 10px;">
                        <p style="margin: 0; font-size: 11px; color: #64748b; font-weight: bold; text-transform: uppercase; letter-spacing: 0.05em;">{{ __('Update Time') }}</p>
                        <p style="margin: 0; font-size: 16px; color: #1e293b; font-weight: 800; margin-top: 4px;">{{ $updateTime }}</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{ __('You can now access the latest insights and analysis on your dashboard.') }}

@component('mail::button', ['url' => route('tracking.email-click', ['user' => $user->id]), 'color' => 'primary'])
{{ $customBtnText }}
@endcomponent

@if($customFooter)
<div style="margin-top: 30px; text-align: center; color: #94a3b8; font-size: 12px;">
    {{ $customFooter }}
</div>
@endif

{{ __('Regards') }},<br>
{{ config('app.name') }}
@endcomponent
