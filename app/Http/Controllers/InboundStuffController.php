<?php

namespace App\Http\Controllers;

use App\Helpers\ApiFormatter;
use Illuminate\Http\Request;
use App\Models\InboundStuff;
use App\Models\Stuff;
use App\Models\StuffStock;
use Illuminate\Support\Str;

class InboundStuffController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function index(Request $request)
    {
        try {
            if($request->filter_id) {
                $data = InboundStuff::where('stuff_id', $request->filter_id)->with('stuff', 'stuff.stuffStock')->get();
            } else {
                $data = InboundStuff::all();
            }

            return ApiFormatter::sendResponse(200, 'success', $data);
        } catch (\Exception $err) {
            return ApiFormatter::sendResponse(400, 'bad request', $err->getMessage());
        }
    }

    public function store(Request $request)
    {
        try {
            $this->validate($request, [
                'stuff_id' => 'required',
                'total' => 'required',
                'date' => 'required',
                // proff_file : type file image (jpg, jpeg, svg, png, webp)
                'proof_file' => 'required|image',
            ]);

            // $request->file() : ambil data yg type nya file
            // getClientOriginalName() : ambil nama asli dari file yg di upload
            // Str::random(jumlah_karakter) : generate random karakter sebanyak jumlah
            $nameImage = Str::random(5) .  "_" . $request->file('proof_file')->getClientOriginalName();
            // move() : memindahkan file yg di upload ke folder public, dan nama file nya mau apa
            $request->file('proof_file')->move('upload-images', $nameImage);
            // ambil url untuk menampilkan gambarnya
            $pathImage = url('upload-images/' . $nameImage);

            $inboundData = InboundStuff::create([
                'stuff_id' => $request->stuff_id,
                'total' => $request->total,
                'date' => $request->date,
                // yg dimasukkan ke db data lokasi url gambarnya
                'proof_file' => $pathImage,
            ]);

            if ($inboundData) {
                $stockData = StuffStock::where('stuff_id', $request->stuff_id)->first();
                if ($stockData) { //kalau data stuffstock yg stuff_id nya kaya yg di buat ada
                    $total_avaliable = (int)$stockData['total_avaliable'] + (int)$request->total; //(int) : memastikan kalau dia integer, klo ngga integer diubah jd integer
                    $stockData->update([ 'total_avaliable' => $total_avaliable ]);
                } else { //kalau stock nya belum ada, dibuat
                    StuffStock::create([
                        'stuff_id' => $request->stuff_id,
                        'total_avaliable' => $request->total, //total_avaliable nya dr inputan total inbound
                        'total_defec' => 0,
                    ]);
                }
                //ambil data mulai dr stuff, inboundStuffs, dan stuffStock dr stuff_id terkait
                $stuffWithInboundAndStock = Stuff::where('id', $request->stuff_id)->with('inboundStuffs', 'stuffStock')->first();
                return ApiFormatter::sendResponse(200, 'success', $stuffWithInboundAndStock);
            }
        } catch (\Exception $err) {
            return ApiFormatter::sendResponse(400, 'bad request', $err->getMessage());
        }
    }
    public function destroy($id)
    {
        try {
            $inboundData = Inboundstuff::where('id', $id)->first();
            $stuffId = $inboundData['stuff_id'];
            $totalInbound = $inboundData['total'];

            $dataStock = StuffStock::where('stuff_id',$inboundData['stuff_id'])->first();
            $total_avaliable = (int)$dataStock['total_avali$total_avaliable'] - (int)$totalInbound;

            if ($total_avaliable < 0) {
                return ApiFormatter::sendResponse(400,'bad request','Jumlah total imbound yang akan dihapus lebih besar dari total available stuff saat ini!');
            }
            
            $inboundData->delete();

            $minusTotalStock = StuffStock::where('stuff_id', $inboundData['stuff_id'])->update(['$total_avaliable' => $total_avaliable]);

            if ($minusTotalStock){
                $updatedStuffWithInboundAndStock = Stuff::where('id', $inboundData['staff_id'])->with('inboundStuffs','stuffStock')->first();
                
                $inboundData->delete();
                return ApiFormatter::sendResponse(200,'Success',$updatedStuffWithInboundAndStock);
            }
        }catch (\Exception $err){
            return ApiFormatter::sendResponse(400,'bad request',$err->getMessage());
        }
    }


    public function trash()
    {
        try {
            $data = InboundStuff::onlyTrashed()->get();

            return ApiFormatter::sendResponse(200, 'success', $data);
        } catch (\Exception $err) {
            return ApiFormatter::sendResponse(400, 'bad request', $err->getMessage());
        }
    }

    public function restore($id)
    {
        try {
            $restore = InboundStuff::onlyTrashed()->where('id', $id)->restore();

            if ($restore) {
                $data = InboundStuff::find($id);
                $stock = StuffStock::where('stuff_id', $data['stuff_id'])->first();
                $total_avaliable = (int)$stock['total_avaliable'] + (int)$data['total'];
                $stock->update(['total_avaliable' => $total_avaliable]);

                $dataResponse = Stuff::where('id', $data['stuff_id'])->with('inboundStuffs', 'stuffStock')->first();
                return ApiFormatter::sendResponse(200, 'success', $dataResponse);
            }
        } catch (\Exception $err) {
            return ApiFormatter::sendResponse(400, 'bad request', $err->getMessage());
        }
    }

    public function permanentDelete($id)
    {
        try {
           $data = InboundStuff::onlyTrashed()->where('id', $id)->first();

           $images = explode("/", $data['proff_file']);
           if (file_exists(public_path('upload-images/' . $images[4]))) {
                unlink(public_path('upload-images/' . $images[4]));
           }

           $data->forceDelete();
           return ApiFormatter::sendResponse(200, 'success', 'Berhasil hapus permanen inbound beserta file nya!');
        } catch (\Exception $err) {
            return ApiFormatter::sendResponse(400, 'bad request', $err->getMessage());
        }
    }
}
