import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../wayfinder'
/**
* @see \FileController::pcap
* @see [unknown]:0
* @route '/file/uploadPcap'
*/
export const pcap = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: pcap.url(options),
    method: 'post',
})

pcap.definition = {
    methods: ["post"],
    url: '/file/uploadPcap',
} satisfies RouteDefinition<["post"]>

/**
* @see \FileController::pcap
* @see [unknown]:0
* @route '/file/uploadPcap'
*/
pcap.url = (options?: RouteQueryOptions) => {
    return pcap.definition.url + queryParams(options)
}

/**
* @see \FileController::pcap
* @see [unknown]:0
* @route '/file/uploadPcap'
*/
pcap.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: pcap.url(options),
    method: 'post',
})

/**
* @see \FileController::pcap
* @see [unknown]:0
* @route '/file/uploadPcap'
*/
const pcapForm = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: pcap.url(options),
    method: 'post',
})

/**
* @see \FileController::pcap
* @see [unknown]:0
* @route '/file/uploadPcap'
*/
pcapForm.post = (options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: pcap.url(options),
    method: 'post',
})

pcap.form = pcapForm

const upload = {
    pcap: Object.assign(pcap, pcap),
}

export default upload