<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.


require_once('../../../../config.php');
require_once('../../lib.php');

$id = required_param('id', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);

if (! $gallery = get_record('lightboxgallery', 'id', $id)) {
    print_error('invalidlightboxgalleryid', 'lightboxgallery');
}
if (! $course = get_record('course', 'id', $gallery->course)) {
    print_error('invalidcourseid');
}
if (! $cm = get_coursemodule_from_instance('lightboxgallery', $gallery->id, $course->id)) {
    print_error('invalidcoursemodule');
}

require_login($course->id);

$context = get_context_instance(CONTEXT_MODULE, $cm->id);

$galleryurl = $CFG->wwwroot . '/mod/lightboxgallery/view.php?id=' . $cm->id;

require_capability('mod/lightboxgallery:edit', $context);

$navigation = build_navigation(get_string('tagsimport', 'lightboxgallery'), $cm);

print_header($course->shortname . ': ' . $gallery->name, $course->fullname, $navigation,
             '', '', true, '&nbsp', navmenu($course, $cm));

$disabledplugins = explode(',', get_config('lightboxgallery', 'disabledplugins'));
if (in_array('tag', $disabledplugins)) {
    error(get_string('tagsdisabled', 'lightboxgallery'));
}

print_spacer();

if ($confirm && confirm_sesskey()) {
    $dataroot = $CFG->dataroot . '/' . $course->id . '/' . $gallery->folder;

    $images = lightboxgallery_directory_images($dataroot);

    $a = new object;
    $a->tags = 0;
    $a->images = count($images);

    if (count($images) > 0) {
        foreach ($images as $image) {
            $path = $dataroot . '/' . $image;
            $size = getimagesize($path, $info);
            if (isset($info['APP13'])) {
                $iptc = iptcparse($info['APP13']);
                if (isset($iptc['2#025'])) {
                    sort($iptc['2#025']);
                    $errorlevel = error_reporting(E_PARSE);
                    $textlib = textlib_get_instance();

                    foreach ($iptc['2#025'] as $tag) {
                        $tag = $textlib->typo3cs->utf8_encode($tag, 'iso-8859-1');
                        $tag = clean_param($tag, PARAM_TAG);
                        $tag = trim(strip_tags($tag));
                        $tag = addslashes(strtolower($tag));
                        $sql_select = "gallery = {$gallery->id} AND image = '$image' AND metatype = 'tag' AND description = '$tag'";
                        if (! record_exists_select('lightboxgallery_image_meta', $sql_select)) {
                            $record = new object;
                            $record->gallery = $gallery->id;
                            $record->image = $image;
                            $record->metatype = 'tag';
                            $record->description = $tag;
                            if ($DB->insert_record('lightboxgallery_image_meta', $record)) {
                                $a->tags++;
                            }
                        }
                    }
                    error_reporting($errorlevel);
                }
            }
        }
    }

    foreach (array_keys((array)$a) as $b) {
        $a->{$b} = number_format($a->{$b});
    }

    notice(get_string('tagsimportfinish', 'lightboxgallery', $a), $galleryurl);
} else {
    notice_yesno(get_string('tagsimportconfirm', 'lightboxgallery'),
        $CFG->wwwroot . '/mod/lightboxgallery/edit/tag/import.php', $CFG->wwwroot . '/mod/lightboxgallery/view.php',
        array('id' => $gallery->id, 'confirm' => 1, 'sesskey' => sesskey()), array('id' => $cm->id, 'editing' => 1),
        'post', 'get'
    );
}

print_footer($course);
