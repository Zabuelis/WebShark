<script setup>
    import axios from 'axios';
    import { ref } from 'vue';

    const file = ref(null);
    const successMessage = ref(null);
    const failureMessage = ref(null);

    const fileChange = (event) =>{
        file.value = event.target.files[0];
    };

    const uploadFile = async() => {
        if(!file.value){
            return;
        }

        try {
            // Form HTML data send
            const fileSendData = new FormData();
            fileSendData.append('pcap_file', file.value);

            const response = await axios.post(`${import.meta.env.VITE_API_BASE_URL}/file/uploadPcap`,
                fileSendData
            );
            successMessage.value = response.data.success;
            failureMessage.value = null;

            
        } catch (error){
            console.log(error);
            failureMessage.value = "File upload failed";
            successMessage.value = null;
        }
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
        <!-- File upload container -->
        <div class = "file-container">
            <!-- Logo -->
            <div class = "flex justify-center items-center">
                <h1 class = "WS-title"> WebShark </h1>
            </div>
            <div class = "flex justify-center items-center">
                <div class = "file-upload">
                    <form @submit.prevent="uploadFile" enctype="multipart/form-data">
                        <div class="col-span-full py-2">
                            <label for="cover" class="block text-center text-lg font-medium">Analyse network traffic files, visualize important data, <br> spot potential vulnerabilities or attacks in your network.</label>
                                <!-- Upload box -->
                                <div class="upload-text py-4 text-center w-100 mx-auto">
                                    <label class="text-base font-medium mb-3 block">Upload file</label>
                                    <input @change="fileChange" type="file" class="w-full font-medium text-sm bg-white border file:cursor-pointer cursor-pointer file:border-0 file:py-3 file:px-4 file:mr-4 file:bg-gray-100 file:hover:bg-gray-200 file:text-slate-500 rounded" />
                                    <p class="text-xs mt-2">PCAP and PCAPNG are allowed up to 10 MB.</p>
                                </div>
                        </div>
                        <div class="flex items-center justify-center gap-x-6">
                            <button type="submit" class="rounded-md bg-indigo-500 px-6 py-2 text-md font-semibold text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500">Analyze</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class = "footer"></div>
    </div>
</template>