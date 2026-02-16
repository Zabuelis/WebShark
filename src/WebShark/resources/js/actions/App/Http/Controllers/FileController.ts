import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\FileController::uploadPcap
* @see app/Http/Controllers/FileController.php:10
* @route '/file/uploadPcap'
*/
export const uploadPcap = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadPcap.url(options),
    method: 'post',
})

uploadPcap.definition = {
    methods: ["post"],
    url: '/file/uploadPcap',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\FileController::uploadPcap
* @see app/Http/Controllers/FileController.php:10
* @route '/file/uploadPcap'
*/
uploadPcap.url = (options?: RouteQueryOptions) => {
    return uploadPcap.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\FileController::uploadPcap
* @see app/Http/Controllers/FileController.php:10
* @route '/file/uploadPcap'
*/
uploadPcap.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: uploadPcap.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\FileController::uploadPcap
* @see app/Http/Controllers/FileController.php:10
* @route '/file/uploadPcap'
*/
const uploadPcapForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: uploadPcap.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\FileController::uploadPcap
* @see app/Http/Controllers/FileController.php:10
* @route '/file/uploadPcap'
*/
uploadPcapForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: uploadPcap.url(options),
    method: 'post',
})

uploadPcap.form = uploadPcapForm

const FileController = { uploadPcap }

export default FileController