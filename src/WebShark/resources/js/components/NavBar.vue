<script setup>
import myLogo from '../../images/logo.png';
import { Link } from '@inertiajs/vue3'

defineProps({
    showShareButton: { type: Boolean, default: false },
    onCopy: { type: Function, default: () => {} }
})
</script>

<template>
    <nav class="navbar-container flex justify-between items-center pr-8 pl-10 border-b-2">
        <!-- Logo -->
        <Link :href="route('home')" class="navbar-title items-center flex gap-2 cursor-pointer">
            <img :src="myLogo" alt="WebShark Logo" class="h-10 w-auto" />
            <span class="">WebShark</span>
        </Link>

        <!-- Share Button -->
        <button 
            v-if="showShareButton" 
            @click="onCopy"
            class="flex items-center gap-2 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-md text-sm font-medium transition-colors"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
            </svg>
            Copy Link
        </button>
    </nav>
</template>
