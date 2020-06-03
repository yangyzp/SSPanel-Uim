<?php

namespace App\Controllers\Admin;

use App\Controllers\AdminController;
use App\Models\{
    Ann,
    User
};
use App\Utils\{
    Telegram,
    DatatablesHelper
};
use App\Services\Mail;
use Ozdemir\Datatables\Datatables;
use Exception;
use Slim\Http\Request;
use Slim\Http\Response;

class AnnController extends AdminController
{
    /**
     * @param Request   $request
     * @param Response  $response
     * @param array     $args
     */
    public function index($request, $response, $args)
    {
        $table_config['total_column'] = array(
            'op' => '操作', 'id' => 'ID',
            'date' => '日期', 'content' => '内容'
        );
        $table_config['default_show_column'] = array(
            'op', 'id',
            'date', 'content'
        );
        $table_config['ajax_url'] = 'announcement/ajax';
        return $this->view()->assign('table_config', $table_config)->display('admin/announcement/index.tpl');
    }

    /**
     * @param Request   $request
     * @param Response  $response
     * @param array     $args
     */
    public function create($request, $response, $args)
    {
        return $this->view()->display('admin/announcement/create.tpl');
    }

    /**
     * @param Request   $request
     * @param Response  $response
     * @param array     $args
     */
    public function add($request, $response, $args)
    {
        $issend = $request->getParam('issend');
        $PushBear = $request->getParam('PushBear');
        $vip = $request->getParam('vip');
        $content = $request->getParam('content');
        $subject = $_ENV['appName'] . '-公告';

        if ($request->getParam('page') == 1) {
            $ann = new Ann();
            $ann->date = date('Y-m-d H:i:s');
            $ann->content = $content;
            $ann->markdown = $request->getParam('markdown');

            if (!$ann->save()) {
                $rs['ret'] = 0;
                $rs['msg'] = '添加失败';
                return $response->withJson($rs);
            }
        }
        if ($PushBear == 1) {
            $PushBear_sendkey = $_ENV['PushBear_sendkey'];
            $postdata = http_build_query(
                array(
                    'text' => $subject,
                    'desp' => $request->getParam('markdown'),
                    'sendkey' => $PushBear_sendkey
                )
            );
            file_get_contents('https://pushbear.ftqq.com/sub?' . $postdata, false);
        }
        if ($issend == 1) {
            $beginSend = ($request->getParam('page') - 1) * $_ENV['sendPageLimit'];
            $users = User::where('class', '>=', $vip)->skip($beginSend)->limit($_ENV['sendPageLimit'])->get();
            foreach ($users as $user) {
                $to = $user->email;
                if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }
                $text = $content;
                try {
                    Mail::send($to, $subject, 'news/warn.tpl', [
                        'user' => $user, 'text' => $text
                    ], []);
                } catch (Exception $e) {
                    continue;
                }
            }
            if (count($users) == $_ENV['sendPageLimit']) {
                $rs['ret'] = 2;
                $rs['msg'] = $request->getParam('page') + 1;
                return $response->withJson($rs);
            }
        }

        Telegram::SendMarkdown('新公告：' . PHP_EOL . $request->getParam('markdown'));
        $rs['ret'] = 1;
        if ($issend == 1 && $PushBear == 1) {
            $rs['msg'] = '公告添加成功，邮件发送和PushBear推送成功';
        }
        if ($issend == 1 && $PushBear != 1) {
            $rs['msg'] = '公告添加成功，邮件发送成功';
        }
        if ($issend != 1 && $PushBear == 1) {
            $rs['msg'] = '公告添加成功，PushBear推送成功';
        }
        if ($issend != 1 && $PushBear != 1) {
            $rs['msg'] = '公告添加成功';
        }
        return $response->withJson($rs);
    }

    /**
     * @param Request   $request
     * @param Response  $response
     * @param array     $args
     */
    public function edit($request, $response, $args)
    {
        $id = $args['id'];
        $ann = Ann::find($id);
        return $this->view()->assign('ann', $ann)->display('admin/announcement/edit.tpl');
    }

    /**
     * @param Request   $request
     * @param Response  $response
     * @param array     $args
     */
    public function update($request, $response, $args)
    {
        $id = $args['id'];
        $ann = Ann::find($id);

        $ann->content = $request->getParam('content');
        $ann->markdown = $request->getParam('markdown');
        $ann->date = date('Y-m-d H:i:s');

        if (!$ann->save()) {
            $rs['ret'] = 0;
            $rs['msg'] = '修改失败';
            return $response->withJson($rs);
        }

        Telegram::SendMarkdown('公告更新：' . PHP_EOL . $request->getParam('markdown'));

        $rs['ret'] = 1;
        $rs['msg'] = '修改成功';
        return $response->withJson($rs);
    }

    /**
     * @param Request   $request
     * @param Response  $response
     * @param array     $args
     */
    public function delete($request, $response, $args)
    {
        $id = $request->getParam('id');
        $ann = Ann::find($id);
        if (!$ann->delete()) {
            $rs['ret'] = 0;
            $rs['msg'] = '删除失败';
            return $response->withJson($rs);
        }
        $rs['ret'] = 1;
        $rs['msg'] = '删除成功';
        return $response->withJson($rs);
    }

    /**
     * @param Request   $request
     * @param Response  $response
     * @param array     $args
     */
    public function ajax($request, $response, $args)
    {
        $datatables = new Datatables(new DatatablesHelper());
        $datatables->query('Select id as op,id,date,content from announcement');

        $datatables->edit('op', static function ($data) {
            return '<a class="btn btn-brand" href="/admin/announcement/' . $data['id'] . '/edit">编辑</a>
                    <a class="btn btn-brand-accent" id="delete" value="' . $data['id'] . '" href="javascript:void(0);" onClick="delete_modal_show(\'' . $data['id'] . '\')">删除</a>';
        });

        $datatables->edit('DT_RowId', static function ($data) {
            return 'row_1_' . $data['id'];
        });

        return $response->write($datatables->generate());
    }
}
