<script setup>
    import { ref, onMounted } from 'vue'
    import { Head, useForm, usePage } from '@inertiajs/vue3'
    import NavBar from '../components/NavBar.vue'
    import Footer from '../components/Footer.vue'

    const props = defineProps({
        cookieError: String
    })

    const localCookieError = ref(props.cookieError);

    // Information related to the page that comes from inertia (flash messages, csfr_token, etc.)
    const pageMessages = usePage()

    // Reacts to changes once the page is loaded
    const file = ref(null);
    const active = ref(false);

    const form = useForm({
        'pcap_file' : null
    })

    // Check for cookies on mount
    onMounted(() => {
        // If navigator says cookies are off, or if the URL has our error param
        if (!navigator.cookieEnabled || new URLSearchParams(window.location.search).get('error') === 'cookies_required') {
            localCookieError.value = "Cookies are required to analyze files. Please enable them and refresh the page.";
        }
    })

    function toggleActive(){
        active.value = !active.value
    }

    // Track file upload 
   function fileChange(event){
        if (event.dataTransfer != null){
            const fileExtension = event.dataTransfer.files[0].name.split('.').pop().toLowerCase()
            if(fileExtension === 'pcap' || fileExtension === 'pcapng'){
                file.value = event.dataTransfer.files[0]
                form.pcap_file = file
            }
            active.value = false
        } else {
            file.value = event.target.files[0]
            form.pcap_file = file
        }
    }

    function submit(){
        if (!navigator.cookieEnabled) {
            localCookieError.value = "Cannot submit: Cookies are disabled.";
            return;
        }

        form.post('/file/uploadPcap', {
            _token : pageMessages.props.csfr_token,
            forceFormData: true,    // Forms including files should be converted to FormData objects
        })
    }

    // Track reset button event
    function resetFile(){
        file.value = null
        form.pcap_file = null
    };

</script>

<template>
    <Head title="Home" />
    <NavBar />

    <div class = "flex-auto min-h-screen">

        <!-- Cookie Specific Error -->
        <div v-if="localCookieError" class="bg-orange-100 border border-orange-400 text-center text-orange-700 px-4 py-3 rounded relative" role="alert">
            <strong class="font-bold">{{ localCookieError }}</strong>
        </div>

        <!-- Feedback messages -->
        <div v-if="pageMessages.props.flash.error" class="bg-red-100 border border-red-400 text-center text-red-700 px-4 py-3 rounded relative" role="alert">
            <strong class="font-bold">{{ pageMessages.props.flash.error }}</strong>
        </div>
        <div v-if="pageMessages.props.flash.success" class = "bg-green-100 border border-green-400 text-center text-green-700 px-4 py-3 rounded relative" role="alert">
            <strong class="font-bold">{{ pageMessages.props.flash.success }}</strong>
        </div>

        <div class="text-center pt-5">
            <h1 class="title-normal">Analyze your network</h1>
            <h1 class="title-gradient">traffic in seconds</h1>
        </div>
        <div class="title-info pt-6 pb-10 text-center">
            <p>Upload a PCAP or PCAPNG capture file and get instant insights <br> into your network packets - protocols, flows and more.</p>
        </div>

        <!-- File upload container -->
        <form @submit.prevent="submit" enctype="multipart/form-data">
            <div class="flex justify-center">
                <div @dragenter.prevent="toggleActive" 
                    @dragleave.prevent="toggleActive" 
                    @dragover.prevent
                    @drop.prevent="fileChange"
                    :class="{'active-dropbox': active}"
                    class="file-dropbox rounded-md">
                    
                        <div class="flex justify-center pt-10">
                            <svg xmlns="http://www.w3.org/2000/svg" height="60" fill="currentColor" class=" bi bi-file-earmark-arrow-up-fill" viewBox="0 0 16 16">
                                <path d="M9.293 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4.707A1 1 0 0 0 13.707 4L10 .293A1 1 0 0 0 9.293 0M9.5 3.5v-2l3 3h-2a1 1 0 0 1-1-1M6.354 9.854a.5.5 0 0 1-.708-.708l2-2a.5.5 0 0 1 .708 0l2 2a.5.5 0 0 1-.708.708L8.5 8.707V12.5a.5.5 0 0 1-1 0V8.707z"/>
                            </svg>
                        </div>
                        <div class="pt-8">
                            <span class="flex justify-center upload-text-primary">Drop your capture file here</span>
                            <p class="flex justify-center upload-text-secondary p-2">or click to browse - PCAP & PCAPNG supported up to 10 MB</p>
                        </div>
                        <div v-if="!file" class="flex py-6 justify-center">
                            <label class="hover:bg-gray-300 bg-gray-200 cursor-pointer browse-files-btn flex justify-center items-center rounded-md">
                                Browse files
                                <input @change="fileChange" type="file" accept=".pcap, .pcapng" class="hidden"/>
                            </label>
                        </div>
                        <div>
                            <div v-if="file" class="display-file-name pt-4 flex justify-center">
                                <p>Selected file:<br> {{ file.name }}</p>
                            </div>
                        </div>
                </div>
            </div>
            <div v-if="file" class="flex justify-center p-5" >
                <div class="w-4xl flex justify-center">
                    <button type="submit" class="hover:bg-gray-300 bg-gray-200 browse-files-btn m-2 cursor-pointer border border-solid rounded-md"> Analyze! </button>
                    <button @click="resetFile" type="button" class="hover:bg-red-300 bg-red-400 m-2 browse-files-btn cursor-pointer border border-solid rounded-md "> Cancel </button>
                </div>
            </div>
        </form>

        <div class="flex justify-center p-15">
            <ul class="flex items-center gap-6">
                <li class="flex rounded-xl informational-cards bg-white items-center px-3"> 
                    <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 0 640 640"><!--!Font Awesome Free v7.2.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc. -->
                        <path d="M434.8 54.1C446.7 62.7 451.1 78.3 445.7 91.9L367.3 288L512 288C525.5 288 537.5 296.4 542.1 309.1C546.7 321.8 542.8 336 532.5 344.6L244.5 584.6C233.2 594 217.1 594.5 205.2 585.9C193.3 577.3 188.9 561.7 194.3 548.1L272.7 352L128 352C114.5 352 102.5 343.6 97.9 330.9C93.3 318.2 97.2 304 107.5 295.4L395.5 55.4C406.8 46 422.9 45.5 434.8 54.1z"/>
                    </svg>
                    Fast parsing
                </li>
                <li class="flex rounded-xl informational-cards bg-white items-center px-3">
                    <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 0 640 640"><!--!Font Awesome Free v7.2.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc. -->
                        <path d="M480 272C480 317.9 465.1 360.3 440 394.7L566.6 521.4C579.1 533.9 579.1 554.2 566.6 566.7C554.1 579.2 533.8 579.2 521.3 566.7L394.7 440C360.3 465.1 317.9 480 272 480C157.1 480 64 386.9 64 272C64 157.1 157.1 64 272 64C386.9 64 480 157.1 480 272zM272 416C351.5 416 416 351.5 416 272C416 192.5 351.5 128 272 128C192.5 128 128 192.5 128 272C128 351.5 192.5 416 272 416z"/>
                    </svg>
                    Packet inspection
                </li>
                <li class="flex rounded-xl informational-cards bg-white items-center px-3">
                    <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 0 640 640"><!--!Font Awesome Free v7.2.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc. -->
                        <path d="M96 96C113.7 96 128 110.3 128 128L128 464C128 472.8 135.2 480 144 480L544 480C561.7 480 576 494.3 576 512C576 529.7 561.7 544 544 544L144 544C99.8 544 64 508.2 64 464L64 128C64 110.3 78.3 96 96 96zM304 160C310.7 160 317.1 162.8 321.7 167.8L392.8 245.3L439 199C448.4 189.6 463.6 189.6 472.9 199L536.9 263C541.4 267.5 543.9 273.6 543.9 280L543.9 392C543.9 405.3 533.2 416 519.9 416L215.9 416C202.6 416 191.9 405.3 191.9 392L191.9 280C191.9 274 194.2 268.2 198.2 263.8L286.2 167.8C290.7 162.8 297.2 160 303.9 160z"/>
                    </svg>
                    Visual analytics
                </li>
                <li class="flex rounded-xl informational-cards bg-white items-center px-3">
                    <svg xmlns="http://www.w3.org/2000/svg" height="20px" viewBox="0 0 640 640"> <!--!Font Awesome Free v7.2.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2026 Fonticons, Inc. -->
                        <path d="M424.5 355.1C449 329.2 464 294.4 464 256C464 176.5 399.5 112 320 112C240.5 112 176 176.5 176 256C176 294.4 191 329.2 215.5 355.1C236.8 377.5 260.4 409.1 268.8 448L371.2 448C379.6 409 403.2 377.5 424.5 355.1zM459.3 388.1C435.7 413 416 443.4 416 477.7L416 496C416 540.2 380.2 576 336 576L304 576C259.8 576 224 540.2 224 496L224 477.7C224 443.4 204.3 413 180.7 388.1C148 353.7 128 307.2 128 256C128 150 214 64 320 64C426 64 512 150 512 256C512 307.2 492 353.7 459.3 388.1zM272 248C272 261.3 261.3 272 248 272C234.7 272 224 261.3 224 248C224 199.4 263.4 160 312 160C325.3 160 336 170.7 336 184C336 197.3 325.3 208 312 208C289.9 208 272 225.9 272 248z"/>
                    </svg>
                    Protocol information
                </li>
            </ul>
        </div>

    </div>
    <Footer />
</template>