@props([
    'compact' => false,
])

@php
    $wrapper = $compact
        ? 'space-y-1.5 rounded-lg border border-base-200 bg-base-200/50 p-3'
        : 'grid grid-cols-1 gap-2 rounded-lg border border-base-200 bg-base-200/50 p-3 sm:grid-cols-2 sm:p-4';
    $row = 'flex items-start gap-2 cursor-pointer';
    $label = $compact ? 'text-xs leading-snug' : 'text-xs leading-snug sm:text-sm';
@endphp

<div {{ $attributes->merge(['class' => $wrapper]) }} data-policy-consents>
    <label class="{{ $row }}">
        <input type="checkbox"
               name="accept_terms"
               value="1"
               class="checkbox checkbox-primary checkbox-sm mt-0.5 shrink-0"
               data-policy-consent
               @checked(old('accept_terms'))>
        <span class="{{ $label }}">
            I agree to the
            <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener" class="link link-primary">Terms of Service</a>.
        </span>
    </label>

    <label class="{{ $row }}">
        <input type="checkbox"
               name="accept_privacy"
               value="1"
               class="checkbox checkbox-primary checkbox-sm mt-0.5 shrink-0"
               data-policy-consent
               @checked(old('accept_privacy'))>
        <span class="{{ $label }}">
            I have read the
            <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener" class="link link-primary">Privacy Policy</a>.
        </span>
    </label>

    <label class="{{ $row }}">
        <input type="checkbox"
               name="accept_cookies"
               value="1"
               class="checkbox checkbox-primary checkbox-sm mt-0.5 shrink-0"
               data-policy-consent
               @checked(old('accept_cookies'))>
        <span class="{{ $label }}">
            I accept the
            <a href="{{ route('legal.cookies') }}" target="_blank" rel="noopener" class="link link-primary">Cookie Policy</a>.
        </span>
    </label>

    <label class="{{ $row }}">
        <input type="checkbox"
               name="confirm_age"
               value="1"
               class="checkbox checkbox-primary checkbox-sm mt-0.5 shrink-0"
               data-policy-consent
               @checked(old('confirm_age'))>
        <span class="{{ $label }}">
            I confirm I am old enough to use Bleep under the laws that apply to me.
        </span>
    </label>
</div>
