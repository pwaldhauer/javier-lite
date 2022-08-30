<?php

// Config
$importFile = 'knuspermagierde.xml';
$template = file_get_contents('layout.php');

$ctx = stream_context_create(array('http' =>
    array(
        'timeout' => 1,
    )
));

$xml = simplexml_load_file($importFile);

foreach ($xml->channel->item as $item) {
    $wp = $item->children('wp', true);
    if ((string)$wp->{'post_type'} == 'attachment') {
        continue;
    }

    $params = [
        'slug' => (string)$wp->post_name ?? (string)$wp->post_id,
        'title' => (string)$item->title,
        'date' => strtotime($item->pubDate),
        'text' => (string)$item->children('content', true)
    ];

    $path = sprintf(
        '%s_%s',
        date('Ymd_His', $params['date']),
        $params['slug']
    );



    if (!is_dir('build')) {
        @mkdir('build');
    }

    if (!is_dir('build/' . $path)) {
        @mkdir('build/' . $path);
    }

    $params['text'] = preg_replace_callback('/img.*src="(.*)"/isU', function ($m) use ($path, $ctx) {
        $nonThumbnail = preg_replace('/-(\d+)x(\d+)\./', '.', $m[1]);

        if (!file_exists('build/' . $path . '/' . basename($nonThumbnail))) {

            printf('Downloading: %s' . PHP_EOL, $nonThumbnail);

            if ($content = @file_get_contents($nonThumbnail, false, $ctx)) {
                file_put_contents('build/' . $path . '/' . basename($nonThumbnail), $content);
            }
        }

        return sprintf('img src="%s"', basename($nonThumbnail));
    }, $params['text']);


    $output = str_replace(
        ['%TITLE%', '%CONTENT%', '%DATE%'],
        [$params['title'], $params['text'], date('Y-m-d H:i:s', $params['date'])],
        $template
    );


    file_put_contents(
        sprintf(
            'build/%s/post.html',
            $path
        ),
        $output
    );
}