<script setup>
import myLogo from '../../images/logo.png';
import { Link } from '@inertiajs/vue3'
</script>

<template>
    <nav class="navbar-container flex justify-between items-center pr-8 pl-10 border-b-2">
        <!-- Logo -->
        <Link :href="route('home')" class="navbar-title items-center flex gap-2 cursor-pointer">
            <img :src="myLogo" alt="WebShark Logo" class="h-10 w-auto" />
            <span class="">WebShark</span>
        </Link>

        <!-- Navigation Links -->
        <div class="navbar-links flex items-center gap-6">
            <Link 
                :href="route('help')" 
                class="hover:text-blue-600 transition duration-150"
                :class="{ 'text-blue-600 font-semibold': $page.component === 'Help' }"
            >
                Help
            </Link>
        </div>
    </nav>
</template>