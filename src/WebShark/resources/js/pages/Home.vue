<script setup>
    import axios from 'axios'
    import { ref } from 'vue'

    const file = ref(null);

    const fileChange = (event) =>{
        file.value = event.target.files[0]
    }

    const uploadFile = async() => {
        if(!file.value){
            return
        }

        try {
            // Form HTML data send
            const fileSendData = new FormData();
            fileSendData.append('pcap_file', file.value)

            const response = await axios.post(`${import.meta.env.VITE_API_BASE_URL}/file/uploadPcap`,
                fileSendData
            );
            console.log(response.data);         
        } catch (error){
            console.error(error);
        }

    }

</script>

<template>
    <div class = "flex-auto">
        <div class = "spacer"></div>
        <!-- File upload form -->
        <div class = "file-container">
            <!-- Logo -->
            <div class = "flex justify-center items-center">
                <h1 class = "WS-title"> WebShark </h1>
            </div>
            <div class = "flex justify-center items-center">
                <div class = "file-upload">
                    <!-- This form action needs to be rewritten with axio and CSRF tokens, currently won't work -->
                    <form @submit.prevent="uploadFile" enctype="multipart/form-data">
                        <div class="col-span-full py-2">
                            <label for="cover" class="block text-center text-lg font-medium ">Analyse network traffic files, visualize important data, <br> spot potential vulnerabilities or attacks in your network.</label>
                                <div class="mt-2 flex justify-center px-6 py-6 border border-dashed border-white/25">
                                    <div class="text-center">
                                        <div class="mt-4 flex text-sm/6 text-gray-400">
                                                <label for="file-upload" class="relative cursor-pointer rounded-md bg-transparent font-semibold text-indigo-400 focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-indigo-500 hover:text-indigo-300">
                                                    <span>Upload a file</span>
                                                    <input id="file-upload" name="pcap_file" type="file" class="sr-only" @change="fileChange" required/>
                                                </label>
                                            <p class="pl-1">or drag and drop</p>
                                        </div>
                                        <p class="text-xs/5 text-gray-400">PCAP, PCAPNG up to 10MB</p>
                                    </div>
                                </div>
                        </div>
                        <div class="mt-6 flex items-center justify-center gap-x-6">
                            <button type="submit" class="rounded-md bg-indigo-500 px-6 py-2 text-md font-semibold text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500">Analyze</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class = "footer"></div>
    </div>
</template>