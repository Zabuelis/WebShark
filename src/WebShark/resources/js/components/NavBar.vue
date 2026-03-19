<script setup>
import myLogo from '../../images/logo.png';
</script>

<template>
    <nav class="navbar-container flex justify-between items-center pr-8 pl-10 border-b-2">
        <!-- Logo -->
        <div class="navbar-title items-center flex gap-2 cursor-pointer" @click="$inertia.visit('/')">
            <img :src="myLogo" alt="WebShark Logo" class="h-10 w-auto" />
            <span class="">WebShark</span>
        </div>

        <!-- Links to informational pages -->
        <!-- <div>
            <ul class="navbar-links flex items-center gap-6">
                <li>
                    <a class="hover:text-gray-800" href="#">Docs</a>
                </li>
                <li>
                    <a class="hover:text-gray-800" href="#">ToS</a>
                </li>
            </ul>
        </div> -->
    </nav>
</template>