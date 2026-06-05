<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\AnalysisJob;
use App\Models\Packet;
use App\Jobs\AnalyzePcap;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PcapController extends Controller
{
    // Upload a file and create a job for analysis
    public function create(Request $request)
    {
        $request->validate([
            'pcap_file' => 'required|file|max:10240',
        ]);

        $sessionID = session()->getId();
        $file = $request->file('pcap_file');

        // Laravel does not distinguish mime type pcap,pcapng and returns an error therefore, a manual check is needed
        if(!in_array($request->file('pcap_file')->getClientOriginalExtension(), ['pcap', 'pcapng'])){

            Log::channel('audit')->warning('UPLOAD_FAILED_EXTENSION', [
                'ip' => $request->ip(),
                'session' => $sessionID,
                'file_name' => $file->getClientOriginalName(),
                'extension' => $file->getClientOriginalExtension(),
                'size_bytes' => $file->getSize()
            ]);

            return redirect()->back()->with('error', 'File upload failed, incorrect file extension');
        }

        try {
            $fileName = $request->file('pcap_file')->getClientOriginalName();

            // Pcaps are stored with a timestamp + session ID prefix
            $rebuiltFileName = now()->format('Y-m-d_h:i:s') . '_' . $sessionID . '_' . $fileName;
            $request->file('pcap_file')->storeAs('pcap', $rebuiltFileName);

            // Dispatch job & log to DB
            $uuid = $this->handleNewJob($rebuiltFileName);

            Log::channel('audit')->info('UPLOAD_SUCCESS', [
                'ip' => $request->ip(),
                'session' => $sessionID,
                'file' => $fileName,
                'size_bytes' => $file->getSize()
            ]);

            return redirect()->route('pcap.status', ['id' => $uuid])->with('success', 'File uploaded successfully, analysis started.');

        } catch (Exception $e) {
            Log::error('File save failed', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Only passes metadata — packets are fetched separately with packets()
     */
    public function show(string $id)
    {
        $job = AnalysisJob::where('analysis_id', $id)->firstOrFail();
        $status = $job->status;

        // Default props
        $props = [
            'id' => $id,
            'status' => $status,
            'progress' => $job->progress_percentage,
            'expires_at' => $job->expires_at
                ? Carbon::parse($job->expires_at)->diffForHumans(['parts' => 2, 'join' => true])
                : null,
            'message'  => '',
            'queue_position' => 0,

            // Metadata used by the Overview tab and time formatting
            'total_bytes' => 0,
            'l3_distribution' => null,
            'l4_distribution' => null,
            'l7_distribution' => null,
            'top_talkers' => null,
            'size_distribution' => null,
            'first_packet_time' => 0,
            'last_packet_time' => 0,
            'total_packets' => 0,
            'total_flows' => 0,
        ];

        switch ($status) {
            // Check the status and queue position of the job
            case 'dispatching':
                $props['queue_position'] = AnalysisJob::where('status', 'dispatching')
                ->where('timestamp', '<=', $job->timestamp)
                ->where('analysis_id', '!=', $id)
                ->count() + 1;
                return Inertia::render('Analysis', $props);
            // If the status == analyzing, queue position is no longer required
            case 'analyzing':
                $props['message'] = 'Analyzing PCAP... Please wait.';
                return Inertia::render('Analysis', $props);
            case 'failed':
                $props['message'] = $job->error_message ?? 'Analysis failed due to a system error. Error code: 3';
                return Inertia::render('Analysis', $props);
            default:
                break;
        }

        // If we are here, everything went well
        $props['total_packets'] = Packet::where('analysis_id', $id)->count();
        $props['total_bytes'] = (int) Packet::where('analysis_id', $id)->sum('captured_packet_length');
        $props['total_flows'] = Packet::where('analysis_id', $id)->whereNotNull('flow')->distinct('flow')->count();

        $first = Packet::where('analysis_id', $id)->orderBy('packet_number', 'asc')->value('timestamp');
        $last = Packet::where('analysis_id', $id)->orderBy('packet_number', 'desc')->value('timestamp');

        // L3 protocol distribution based on amount of packets containing L3 data
        $props['l3_distribution'] = Packet::select('l3_protocol as protocol_name',  DB::raw('count (*) as records'))
            ->where('analysis_id', $id)
            ->whereNotNull('l3_protocol')
            ->groupBy('protocol_name')
            ->get();

        // L4 protocol distribution based on amount of packets containing L4 data
        $props['l4_distribution'] = Packet::select('l4_protocol as protocol_name',  DB::raw('count (*) as records'))
            ->where('analysis_id', $id)
            ->whereNotNull('l4_protocol')
            ->groupBy('protocol_name')
            ->get();

        // 15 IP addresses with highest amount of packets sent
        $props['top_talkers'] = Packet::select(DB::raw("l3_attributes->>'Source_IP' as ip"), DB::raw('count (*) as records'))
            ->where('analysis_id', $id)
            ->whereNotNull(DB::raw("l3_attributes->>'Source_IP'"))
            ->groupBy(DB::raw("l3_attributes->>'Source_IP'"))
            ->orderBy('records', 'desc')
            ->limit(15)
            ->get();

        // Create 10 buckets of sizes (0 - 1500)
        $props['size_distribution'] = Packet::select(DB::raw('count (*) as packet_amount'), DB::raw('width_bucket(captured_packet_length, 0, 1501, 10) as packet_size'))
            ->where('analysis_id', $id)
            ->whereNotNull('captured_packet_length')
            ->groupBy('packet_size')
            ->orderBy('packet_size', 'desc')
            ->get();
        
        // L7 protocol distribution based on amount of packets containing L7 data
        $props['l7_distribution'] = Packet::select(DB::raw("l7_attributes->>'Protocol' as protocol_name"), DB::raw('count (*) as records'))
            ->where('analysis_id', $id)
            ->whereRaw("l7_attributes->>'Protocol' IS NOT NULL")
            ->groupBy('protocol_name')
            ->get();

        $props['first_packet_time'] = $first ? (float) $first : 0;
        $props['last_packet_time'] = $last ? (float) $last : 0;

        return Inertia::render('Analysis', $props);
    }

    /**
     * JSON for virtual-scrolling packets
     */
    public function packets(Request $request, string $id): JsonResponse
    {
        $job = AnalysisJob::where('analysis_id', $id)->firstOrFail();

        if ($job->status !== 'finished') {
            return response()->json(['error' => 'Analysis not complete.'], 409);
        }

        $perPage = min((int) $request->query('per_page', 100), 500);
        $page = max((int) $request->query('page', 1), 1);
        $query = trim($request->query('q', ''));
        $query = explode("&&", $query);

        $queryBuilder = Packet::where('analysis_id', $id);

        if (!empty($query) && $query[0] != '') {
            $this->buildFilterQuery($queryBuilder, $query);
        }

        $total = $queryBuilder->count();

        $packets = $queryBuilder
            ->orderBy('packet_number', 'asc')
            ->forPage($page, $perPage)
            ->get([
                'packet_id',
                'packet_number',
                'timestamp',
                'l3_protocol',
                'l3_attributes',
                'l4_protocol',
                'l4_attributes',
                'l7_attributes',
                'flow',
                'captured_packet_length',
            ]);

        return response()->json([
            'data' => $packets,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ]);
    }

    // JSON for virtual-scrolling flows
    public function flows(Request $request, string $id){
        $job = AnalysisJob::where('analysis_id', $id)->firstOrFail();

        if ($job->status !== 'finished') {
            return response()->json(['error' => 'Analysis not complete.'], 409);
        }

        $perPage = min((int) $request->query('per_page', 100), 500);
        $page = max((int) $request->query('page', 1), 1);
        $query = trim($request->query('q', ''));
        $query = explode("&&", $query);

        $queryBuilder = Packet::where('analysis_id', $id)->where('l4_protocol', 'TCP')->whereNotNull('flow');

        if (!empty($query) && $query[0] != '') {
            $this->buildFilterQuery($queryBuilder, $query);
        }

        $total = $queryBuilder->count();

        $packets = $queryBuilder
            ->orderBy('flow', 'asc')
            ->orderBy('packet_number', 'asc')
            ->forPage($page, $perPage)
            ->get([
                'packet_id',
                'packet_number',
                'timestamp',
                'l3_protocol',
                'l3_attributes',
                'l4_protocol',
                'l4_attributes',
                'l7_attributes',
                'flow',
                'captured_packet_length',
            ]);

        return response()->json([
            'data' => $packets,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ]);
    }

    // Build a multiple condition filter
    private function buildFilterQuery($queryBuilder, $query){
        // Key needs to match the actual column in the DB
        $filters = [
            "l3_attributes->>'Source_IP'" => 'ip.src == ',
            "l3_attributes->>'Destination_IP'" => 'ip.dst == ',
            "l4_attributes->>'Destination_Port'" => 'port.dst == ',
            "l4_attributes->>'Source_Port'" => 'port.src == ',
            'proto' => 'proto == ',
            'flow' => 'tcp.flow == ',
        ];
        foreach($query as $term){
            $term = trim($term);
            foreach($filters as $column => $filter){
                if(str_contains($term, $filter)){
                    $value = str_replace($filter, '', $term);
                    switch ($filter) {
                        // Special case where filtering takes up more than 1 column and needs to be aggregated.
                        case 'proto == ':
                            $queryBuilder->where(function ($q) use ($value) {
                                $q->orWhere('l3_protocol', 'like', $value);
                                $q->orWhere('l4_protocol', 'like', $value);
                                $q->orWhereRaw("l7_attributes->>'Protocol' like ?", [$value]);
                            });
                            break;
                        // Special case where the value is a digit
                        case 'tcp.flow == ':
                            $queryBuilder->where($column, '=', $value);
                            break;
                        // Everything else is a string match
                        default:
                            $queryBuilder->whereRaw("{$column} like ?", [$value]);
                            break;
                    }
                }
            }
        }
    }

    /**
     * Dispatch analysis job and log it into db under status 'dispatching'
     */
    private function handleNewJob($rebuiltFileName)
    {
        $uuid = (string) Str::uuid();

        AnalysisJob::insert([
            'analysis_id' => $uuid,
            'file_path' => $rebuiltFileName,
            'status' => 'dispatching',
            'timestamp' => Carbon::now(),
        ]);

        AnalyzePcap::dispatch($uuid, $rebuiltFileName);

        return $uuid;
    }
}