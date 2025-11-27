// Plupload configuration for chunked uploads
var uploader = new plupload.Uploader({
    runtimes : 'html5,flash,silverlight,html4',
    browse_button : 'browse', // you can pass in id...
    container: document.getElementById('container'),
    url : 'upload.php',

    flash_url : 'plupload/Moxie.swf',
    silverlight_xap_url : 'plupload/Moxie.xap',

    filters : {
        max_file_size : '20mb',
        mime_types: [        
            {title : 'Image files', extensions : 'jpg,jpeg,gif,png'},
            {title : 'Zip files', extensions : 'zip'},
            {title : 'All files', extensions : '*'}       
        ]
    },

    // 'chunk_size' sets the size of each chunk to be uploaded
    chunk_size: '1mb',
    // Enables chunked uploads
    multipart: true,
    multipart_params: {
        // parameters to be passed on each chunk
        param1: 'value1',
        param2: 'value2'
    },

    init: {
        PostInit: function() {
            document.getElementById('files').innerHTML = '';
            document.getElementById('upload_btn').onclick = function() {
                uploader.start();
                return false;
            };
        },

        FilesAdded: function(up, files) {
            plupload.each(files, function(file) {
                document.getElementById('files').innerHTML += "<div>" + file.name + " (<b>" + plupload.formatSize(file.size) + "</b>)</div>";
            });
            uploader.start();
        },

        ChunkUploaded: function(up, file, info) {
            // Handle any post-processing after chunk upload here
            console.log('Chunk uploaded: ' + file.name);
        },

        FileUploaded: function(up, file, info) {
            // Handle any post-processing after file upload here
            console.log('File uploaded: ' + file.name);
        },

        Error: function(up, err) {
            console.log("Error: " + err.code + ', Message: ' + err.message);
        }
    }
});

uploader.init();