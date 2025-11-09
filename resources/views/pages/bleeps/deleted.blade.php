<x-layout>
    <x-slot:title>Bleep Deleted</x-slot:title>

    <div class="max-w-4xl mx-auto my-2">
        <a href="/" class="text-md link link-ghost mb-4 inline-block">
            <i data-lucide="arrow-left" class="w-5 h-5 inline-block"></i>
            Back
        </a>

        <div class="bg-base-100/70 rounded-lg shadow-md p-12 text-center">
            <div class="flex flex-col items-center justify-center space-y-4">
                <div class="bg-error/10 rounded-full p-6">
                    <i data-lucide="trash-2" class="w-16 h-16 text-error"></i>
                </div>

                <h2 class="text-2xl font-bold text-base-content">
                    Bleep Deleted
                </h2>

                <p class="text-base-content/70 max-w-md">
                    @if($deletedByAuthor)
                        This bleep was deleted by the author.
                    @else
                        This bleep is no longer available.
                    @endif
                </p>

                <div class="pt-4">
                    <a href="/" class="btn btn-primary">
                        <i data-lucide="home" class="w-4 h-4 mr-2"></i>
                        Go to Home Page
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-layout>
