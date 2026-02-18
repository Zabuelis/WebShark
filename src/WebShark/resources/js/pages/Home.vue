<script setup>
    import axios from 'axios';
    import { ref } from 'vue';

    const file = ref(null);
    const successMessage = ref(null);
    const failureMessage = ref(null);

    // Track file upload 
    function fileChange(event){
        file.value = event.target.files[0];
    };

    // Track reset button event
    function resetFile(){
        file.value = null;
    };

    // Track file.value change
    async function uploadFile(){
        if(!file.value){
            return;
        }

        try {
            // HTML form build
            const fileSendData = new FormData();
            fileSendData.append('pcap_file', file.value);

            const response = await axios.post(`${import.meta.env.VITE_APPLICATION_BASE_URL}/file/uploadPcap`,
                fileSendData
            );

            successMessage.value = response.data.success;
            failureMessage.value = null;

            
        } catch (error){
            console.log(error);
            failureMessage.value = "File upload failed";
            successMessage.value = null;
        }
        // Reset file value after action
        file.value = null;
    }

</script>

<template>
    <div class = "flex-auto">
        <!-- Feedback messages -->
         <div v-if="failureMessage" class="bg-red-100 border border-red-400 text-center text-red-700 px-4 py-3 rounded relative" role="alert">
            <strong class="font-bold">{{ failureMessage }}</strong>
        </div>
        <div v-if="successMessage" class = "bg-green-100 border border-green-400 text-center text-green-700 px-4 py-3 rounded relative" reole="allert">
            <strong class="font-bold">{{ successMessage }}</strong>
        </div>

        <div class = "spacer"></div>
        <!-- Logo -->
        <div class = "flex justify-center">
            <h1 class = "WS-title"> WebShark </h1>
        </div>
        <div class = "text-center py-4 text-lg">
            <p>Upload network files, analyze packets, visualize data,<br>prevent potential network attacks.</p>
        </div>
        <!-- File upload container -->
        <div class = "file-container">
            <div class="flex justify-center py-4">
                <svg xmlns="http://www.w3.org/2000/svg" height="60" fill="currentColor" class="file-icon bi bi-file-earmark-arrow-up-fill" viewBox="0 0 16 16">
                    <path d="M9.293 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4.707A1 1 0 0 0 13.707 4L10 .293A1 1 0 0 0 9.293 0M9.5 3.5v-2l3 3h-2a1 1 0 0 1-1-1M6.354 9.854a.5.5 0 0 1-.708-.708l2-2a.5.5 0 0 1 .708 0l2 2a.5.5 0 0 1-.708.708L8.5 8.707V12.5a.5.5 0 0 1-1 0V8.707z"/>
                </svg>
            </div>
            <form @submit.prevent="uploadFile" enctype="multipart/form-data">
                <div class="flex justify-center items-center">
                    <label v-if="!file" class="file-select-btn cursor-pointer text-center border rounded-md px-4 py-2">
                        Upload file
                        <input @change="fileChange" type="file" class="hidden"/>
                    </label>
                </div>
                <div v-if="!file" class="upload-text text-center py-5">
                    <p>PCAP and PCAPNG files are allowed, up to 10MB</p>
                </div>
                <div v-if="file" class="display-file-name flex justify-center">
                    <p>{{ file.name }}</p>
                </div>
                <div v-if="file" class="flex justify-center py-4" >
                    <button type="submit" class="file-upload-btn cursor-pointer border border-solid rounded-md"> Analyze! </button>
                    <button @click="resetFile" type="button" class="file-upload-btn cursor-pointer border border-solid rounded-md "> Reset </button>
                </div>
            </form>
        </div>
        <div class = "footer"></div>
    </div>
</template>