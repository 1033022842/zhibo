<?php

namespace app\admin\controller\user;

use Throwable;
use app\common\controller\Backend;
use app\admin\model\live\LiveUser as LiveUserModel;

class LiveUser extends Backend
{
    /**
     * @var object
     * @phpstan-var LiveUserModel
     */
    protected object $model;

    protected array $withJoinTable = [];

    protected string|array $preExcludeFields = [];

    protected string|array $quickSearchField = ['nickname', 'id', 'user_no'];

    protected string|array $defaultSortField = ['id' => 'desc'];

    protected bool $modelValidate = false;

    public function initialize(): void
    {
        parent::initialize();
        $this->model = new LiveUserModel();
    }

    public function index(): void
    {
        list($where, $alias, $limit, $order) = $this->queryBuilder();

        $res = $this->model
            ->field('
                live_user.id, live_user.user_no, live_user.nickname, live_user.avatar,
                live_user.email, live_user.status, live_user.level, live_user.created_at,
                IFNULL(lp_user_auth.auth_key, \'\') as auth_account,
                IFNULL(lp_user_auth.auth_type, \'\') as auth_type,
                IFNULL(lp_user_profile.last_login_ip, \'\') as last_login_ip,
                IFNULL(lp_user_profile.last_login_at, NULL) as last_login_at
            ')
            ->alias($alias)
            ->leftJoin('lp_user_auth', 'lp_user_auth.user_id = live_user.id')
            ->leftJoin('lp_user_profile', 'lp_user_profile.user_id = live_user.id')
            ->where($where)
            ->order($order)
            ->paginate($limit);

        $this->success('', [
            'list'   => $res->items(),
            'total'  => $res->total(),
            'remark' => get_route_remark(),
        ]);
    }

    public function add(): void
    {
        $this->error('直播平台用户不支持手动添加，请通过注册流程创建');
    }

    public function del(): void
    {
        $pk  = $this->model->getPk();
        $ids = $this->request->param($pk);
        if (!$ids) {
            $this->error(__('Parameter error'));
        }

        $where = [];
        if (str_contains($ids, ',')) {
            $where[] = [$pk, 'in', $ids];
        } else {
            $where[] = [$pk, '=', $ids];
        }

        $this->model->startTrans();
        try {
            foreach ($this->model->where($where)->select() as $user) {
                $user->profile()->delete();
                $user->auth()->delete();
                $user->delete();
            }
            $this->model->commit();
        } catch (Throwable $e) {
            $this->model->rollback();
            $this->error($e->getMessage());
        }

        $this->success(__('Deleted successfully'));
    }

    public function quickSearch(): void
    {
        $search = $this->request->get('search', '');
        $users  = $this->model
            ->field('id, user_no, nickname')
            ->where('nickname', 'like', "%{$search}%")
            ->whereOr('user_no', 'like', "%{$search}%")
            ->limit(20)
            ->select();

        $list = [];
        foreach ($users as $user) {
            $list[] = [
                'id'       => $user->id,
                'user_no'  => $user->user_no,
                'nickname' => $user->nickname,
                'label'    => $user->nickname . '(ID:' . $user->id . ')',
            ];
        }

        $this->success('', ['list' => $list]);
    }
}
