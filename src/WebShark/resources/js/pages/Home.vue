<script setup>
    import { ref } from 'vue'
    import { Head, useForm, usePage } from '@inertiajs/vue3'
    import NavBar from '../components/NavBar.vue'


    // Information related to the page that comes from inertia (flash messages, csfr_token, etc.)
    const pageMessages = usePage()

    // Reacts to changes once the page is loaded
    const file = ref(null);
    const active = ref(false);

    const form = useForm({
        'pcap_file' : null
    })

    function toggleActive(){
        active.value = !active.value
    }

    // Track file upload 
    function fileChange(event){
        file.value = event.target.files[0]
        form.pcap_file = file
    }

    function submit(){
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

    <div class = "flex-auto">
        <!-- Feedback messages -->
        <div v-if="pageMessages.props.flash.error" class="bg-red-100 border border-red-400 text-center text-red-700 px-4 py-3 rounded relative" role="alert">
            <strong class="font-bold">{{ pageMessages.props.flash.error }}</strong>
        </div>
        <div v-if="pageMessages.props.flash.success" class = "bg-green-100 border border-green-400 text-center text-green-700 px-4 py-3 rounded relative" role="alert">
            <strong class="font-bold">{{ pageMessages.props.flash.success }}</strong>
        </div>

        <div class = "spacer"></div>

        <div class="text-center">
            <h1 class="title-normal">Analyze your network</h1>
            <h1 class="title-gradient">traffic in seconds</h1>
        </div>
        <div class="title-info pt-6 pb-20 text-center">
            <p>Upload a PCAP or PCAPNG capture file and get instant insights <br> into your network packets - protocols, flows and more.</p>
        </div>

        <!-- File upload container -->
        <div class="flex justify-center pb-15">
            <div @dragenter.prevent="toggleActive" 
                @dragleave.prevent="toggleActive" 
                @dragover.prevent
                @drop.prevent="toggleActive"
                :class="{'active-dropzone': active}"
                class="file-dropbox rounded-md">
                <form @submit.prevent="submit" enctype="multipart/form-data">
                    <div class="flex justify-center pt-10">
                        <svg xmlns="http://www.w3.org/2000/svg" height="60" fill="currentColor" class=" bi bi-file-earmark-arrow-up-fill" viewBox="0 0 16 16">
                            <path d="M9.293 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4.707A1 1 0 0 0 13.707 4L10 .293A1 1 0 0 0 9.293 0M9.5 3.5v-2l3 3h-2a1 1 0 0 1-1-1M6.354 9.854a.5.5 0 0 1-.708-.708l2-2a.5.5 0 0 1 .708 0l2 2a.5.5 0 0 1-.708.708L8.5 8.707V12.5a.5.5 0 0 1-1 0V8.707z"/>
                        </svg>
                    </div>
                    <div class="pt-8 text-center">
                        <span class="upload-text-primary">Drop your capture file here</span>
                        <p class="upload-text-secondary pt-1">or click to browse - PCAP & PCAPNG supported up to 10 MB</p>
                    </div>

                    <div v-if="!file" class="flex py-6 justify-center">
                        <label class="hover:bg-gray-300 bg-gray-200 cursor-pointer browse-files-btn flex justify-center items-center rounded-md">
                            Browse files
                            <input @change="fileChange" type="file" accept=".pcap, .pcapng" class="hidden"/>
                        </label>
                    </div>
                    <div>
                        <div v-if="file" class="display-file-name pt-4 flex justify-center">
                            <span>Selected file:  </span>
                            <p>{{ file.name }}</p>
                        </div>
                        <div v-if="file" class="flex justify-center pt-2" >
                            <button type="submit" class="hover:bg-gray-300 bg-gray-200 browse-files-btn m-2 cursor-pointer border border-solid rounded-md"> Analyze! </button>
                            <button @click="resetFile" type="button" class="hover:bg-gray-300 bg-gray-200 m-2 browse-files-btn cursor-pointer border border-solid rounded-md "> Reset </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <div class = "footer"></div>
    </div>
</template>