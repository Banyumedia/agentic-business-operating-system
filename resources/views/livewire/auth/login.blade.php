<div>
    <form wire:submit="login" class="space-y-6">
        <div>
            <label for="email" class="block text-sm font-medium text-slate-700">
                Email address
            </label>
            <div class="mt-1">
                <input wire:model="email" id="email" name="email" type="email" autocomplete="email" required
                       class="appearance-none block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm placeholder-slate-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
            </div>
            @error('email') <span class="mt-2 text-sm text-red-600 block">{{ $message }}</span> @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-slate-700">
                Password
            </label>
            <div class="mt-1">
                <input wire:model="password" id="password" name="password" type="password" autocomplete="current-password" required
                       class="appearance-none block w-full px-3 py-2 border border-slate-300 rounded-md shadow-sm placeholder-slate-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
            </div>
            @error('password') <span class="mt-2 text-sm text-red-600 block">{{ $message }}</span> @enderror
        </div>

        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <input wire:model="remember" id="remember_me" name="remember_me" type="checkbox"
                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-slate-300 rounded">
                <label for="remember_me" class="ml-2 block text-sm text-slate-900">
                    Remember me
                </label>
            </div>
        </div>

        <div>
            <button type="submit"
                    class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                Sign in
                <div wire:loading wire:target="login" class="ml-2">
                    <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
            </button>
        </div>
    </form>
</div>
