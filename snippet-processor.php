<?php
$en = file('./en.txt');
$zh = file('./cn.txt');

$new = 'r:\new.txt';

const STATUS_IN_COMMENT = 1;
const STATUS_IN_ENTITY = 2;

set_exception_handler(function ($e) {
    echo "Uncaught exception: ", $e->getMessage(), PHP_EOL;
});

//预处理
$en = array_map('trim', $en);
$zh = array_map('trim', $zh);
$enDict = $zhDict = [];

process_dict($en, $enDict);
process_dict($zh, $zhDict);

file_put_contents($new, '');

//当前处理行号
$i = $j = 0;

//找到第一个空行（数据开始的前一行）
while (!empty($en[$i++])) ;
while (!empty($zh[$j])) {
    translation_file_append($zh[$j++]);
}

$status = 0;
$status_key = '';
$status_quote = "'";
$content = $en;

for (; $i < count($content); $i++) {
    $line = $content[$i];
    if ($status === 0) {
        if (substr($line, 0, 4) === '<!--') {
            translation_file_append($line);
            if (substr($line, -3) === '-->') {//单行注释
            } else { //多行注释
                $status = STATUS_IN_COMMENT;
            }
        } elseif (substr($line, 0, 8) === '<!ENTITY') {
            $idx = strpos($line, ' ', 9);
            if ($idx === false) { //唉。。。oci.arg.statement.id的特殊处理
                $status_key = substr($line, 9);
                $status_quote = $content[$i + 1][0];
            } else {
                $status_key = substr($line, 9, $idx - 9);
                $status_quote = $line[$idx + 1];
            }

            //根据key去查看是否有翻译
            if (!empty($zhDict['entity'][$status_key])) {
                //如果有翻译要查看行数是否超出英文版
                list($start, $end) = $zhDict['entity'][$status_key];
                list($start2, $end2) = $enDict['entity'][$status_key];
                if ($end - $start > $end2 - $start2) {//如果不幸超出了，那还是人工处理吧
                    var_dump($end, $start, $end2, $start2);
                    exit("entity translation too long: $status_key");
                } else { //不然就可以愉快地使用翻译了
                    //注意！存在原文变更翻译内容要修改的情况，程序并无法自动识别！
                    //注意！存在原文变更翻译内容要修改的情况，程序并无法自动识别！
                    //注意！存在原文变更翻译内容要修改的情况，程序并无法自动识别！
                    translation_file_append_dict($zh, $start, $end);

                    //用于pass在线检查的辅助工具
                    //检查<entry>
                    translation_tag_check($enDict['entity'][$status_key], $zhDict['entity'][$status_key],
                        function ($en, $zh) {
                            return substr_count($en, '</entry>') === substr_count($zh, '</entry>');
                        }, function ($key) {
                            echo "warning: inconsistent <entry> in ", $key, PHP_EOL;
                        }, $status_key);

                    for ($j = 0; $j < $end2 - $start2 - ($end - $start); $j++) {
                        //如果翻译比原文行数少，补空行来保证行号对齐
                        translation_file_append();
                    }
                }
            } else {
                //如果没有翻译，那么就拷贝英文原版先
                echo "warning: missing translation for entity ", $status_key, PHP_EOL;
                list($start2, $end2) = $enDict['entity'][$status_key];
                translation_file_append_dict($en, $start2, $end2);
            }

            if (substr($line, -2) === $status_quote . '>') {
                //单行内容，可以直接pass了
            } else {
                //多行，那么接下来的都可以跳过了（因为已经根据dict处理过了）
                $status = STATUS_IN_ENTITY;
            }
        } elseif (substr($line, 0, 5) === '<?xml') {
            exit('unexpected xml tag at line' . $i);
        } elseif (empty($line)) {
            translation_file_append($line);
        } else {
            exit('unexpected data at line' . $i);
        }
    } elseif ($status === STATUS_IN_COMMENT) {//多行注释中
        translation_file_append($line);
        if (substr($line, -3) === '-->') {
            $status = 0;
        }
    } elseif ($status === STATUS_IN_ENTITY) {
        if (substr($line, -2) === $status_quote . '>') {
            $status = 0;
        }
    }
}

translation_file_append();
translation_file_append();
translation_file_append('<!-- Todo: these should be removed -->');
foreach($zhDict['entity'] as $k => $v) {
	if(empty($enDict['entity'][$k])) { //不存在对应的en项
		echo "warning: the $k entity was removed from en document",PHP_EOL;
		//出于兼容目的，还是先把它留下
		translation_file_append_dict($zh, $v[0], $v[1]);
	}
}

exit('done');

function translation_file_append($content = '')
{
    global $new;
    file_put_contents($new, $content . PHP_EOL, FILE_APPEND);
}

function translation_file_append_dict($content, $start, $end)
{
    for ($i = $start; $i <= $end; $i++) {
        translation_file_append($content[$i]);
    }
}

function translation_tag_check($en_index, $zh_index, $check_callback, $fail_callback, $fail_param)
{
    global $en, $zh;
    $en_content = join(PHP_EOL, array_slice($en, $en_index[0], $en_index[1] - $en_index[0] + 1));
    $zh_content = join(PHP_EOL, array_slice($zh, $zh_index[0], $zh_index[1] - $zh_index[0] + 1));
    if ($check_callback($en_content, $zh_content) === false) {
        $fail_callback($fail_param);
    }
}

function process_dict(array $content, array &$dict)
{

    $status = 0;
    $status_key = '';
    $status_quote = "'";

    for ($i = 0; $i < count($content); $i++) {
        $line = $content[$i];
        if ($status === 0) {
            if (substr($line, 0, 4) === '<!--') {
                if (substr($line, -3) === '-->') {//单行注释
                    $title = trim(substr($line, 5, -3));
                    $dict['comment'][$title] = [$i, $i];
                } else { //多行注释
                    $status_key = md5($line);
                    $status = STATUS_IN_COMMENT;
                    $dict['multicomment'][$status_key] = [$i];
                }
            } elseif (substr($line, 0, 8) === '<!ENTITY') {
                $idx = strpos($line, ' ', 9);
                if ($idx === false) { //唉。。。oci.arg.statement.id的特殊处理
                    $status_key = substr($line, 9);
                    $status_quote = $content[$i + 1][0];
                } else {
                    $status_key = substr($line, 9, $idx - 9);
                    $status_quote = $line[$idx + 1];
                }
                if (substr($line, -2) === $status_quote . '>') {
                    $dict['entity'][$status_key] = [$i, $i];
                } else {
                    $status = STATUS_IN_ENTITY;
                    $dict['entity'][$status_key] = [$i];
                }
            } elseif (substr($line, 0, 5) === '<?xml') {
                continue;
            } elseif (empty($line)) {
                continue;
            } else {
                exit('unexpected data at line' . $i);
            }
        } elseif ($status === STATUS_IN_COMMENT) {//多行注释中
            if (substr($line, -3) === '-->') {
                $dict['multicomment'][$status_key][] = $i;
                $status = 0;
                $status_key = '';
            }
        } elseif ($status === STATUS_IN_ENTITY) {
            if (substr($line, -2) === $status_quote . '>') {
                $dict['entity'][$status_key][] = $i;
                $status = 0;
                $status_key = '';
            }
        }
    }
}
