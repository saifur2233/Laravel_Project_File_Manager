<?php

namespace App\Http\Controllers;

use App\Picture;
use Croppa;
use Exception;
use FileUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class PictureController extends Controller
{
    public $folder = '/uploads/'; // add slashes for better url handling

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // get all pictures
        $pictures = Picture::all();

        // add properties to pictures
        $pictures->map(function ($picture) {
            $picture['size'] = File::size(public_path($picture['url']));
            $picture['thumbnailUrl'] = Croppa::url($picture['url'], 80, 80, ['resize']);
            $picture['deleteType'] = 'DELETE';
            $picture['deleteUrl'] = route('pictures.destroy', $picture->id);
            return $picture;
        });

        // show all pictures
        return response()->json(['files' => $pictures]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {

            // create upload path if it does not exist
            $path = public_path($this->folder);
            if (!File::exists($path)) {
                File::makeDirectory($path);
            };

            // Simple validation (max file size 4GB and only two allowed mime types)
            $validator = new FileUpload\Validator\Simple('4G', ['image/png', 'image/jpg', 'image/jpeg']);

            $data = [];
            foreach ($request->file('files') as $file) {
                // Generate a unique name for the file
                $filesize = $file->getSize();
                $filename = uniqid() . '.' . $file->getClientOriginalExtension();
                $file->move($path, $filename);

                // Set the file URL
                $url = $this->folder . $filename;

                // Save data to the database
                $picture = Picture::create([
                    'name' => $filename,
                    'url' => $url,
                ]);

                // Prepare response data
                $data[] = [
                    'size' => $filesize,
                    'name' => $filename,
                    'url' => $url,
                    'thumbnailUrl' => Croppa::url($url, 80, 80, ['resize']),
                    'deleteType' => 'DELETE',
                    'deleteUrl' => route('pictures.destroy', $picture->id),
                ];
                // output uploaded file response
                return response()->json(['files' => $data]);
            }
        } catch (Exception $e) {
            Log::error('File upload error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Picture  $picture
     * @return \Illuminate\Http\Response
     */
    public function destroy(Picture $picture)
    {
        Croppa::delete($picture->url); // delete file and thumbnail(s)
        $picture->delete(); // delete db record
        return response()->json([$picture->url]);
    }
}
