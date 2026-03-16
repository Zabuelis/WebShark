import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../../../wayfinder'
/**
* @see \App\Http\Controllers\FileController::create
* @see app/Http/Controllers/FileController.php:19
* @route '/file/uploadPcap'
*/
export const create = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: create.url(options),
    method: 'post',
})

create.definition = {
    methods: ["post"],
    url: '/file/uploadPcap',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\FileController::create
* @see app/Http/Controllers/FileController.php:19
* @route '/file/uploadPcap'
*/
create.url = (options?: RouteQueryOptions) => {
    return create.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\FileController::create
* @see app/Http/Controllers/FileController.php:19
* @route '/file/uploadPcap'
*/
create.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: create.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\FileController::create
* @see app/Http/Controllers/FileController.php:19
* @route '/file/uploadPcap'
*/
const createForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: create.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\FileController::create
* @see app/Http/Controllers/FileController.php:19
* @route '/file/uploadPcap'
*/
createForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: create.url(options),
    method: 'post',
})

create.form = createForm

const FileController = { create }

export default FileController