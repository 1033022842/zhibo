<?php
declare(strict_types=1);

namespace app\api\controller;

use app\ai\model\AiTask;
use app\BaseController;
use app\common\enums\TaskStatus;
use app\common\exception\BusinessException;
use app\common\util\StrHelper;
use app\common\web\ResultCode;
use app\live\service\VipService;
use think\App;
use think\facade\Db;
use think\facade\Log;

/**
 * VIP 定制角色视频（AI 电脑 ComfyUI MiniMax-H3 生成）
 */
final class CustomVideo extends BaseController
{
    /** 每日生成配额（AI 电脑单卡串行，防爆队列） */
    private const DAILY_LIMIT = 3;

    /** 生成预设 → H3 时间线导演台结构化提示词（subject_definitions/summary/分镜/音效/配乐） */
    private const PRESETS = [
        [
            'key'   => 'dance',
            'label' => 'Dance clip',
            'desc'  => 'She dances to the beat in a studio',
            'prompt' => "subject_definitions:\n<Subject 1> is the young woman in <Picture 1>, with her facial features, hairstyle and skin tone exactly matching <Picture 1>, a natural figure, standing relaxed.\n\nsummary:\n[reference generation] A 10-second studio dance clip. <Subject 1> from <Picture 1> dances to a rhythmic pop track against a clean light-gray backdrop, starting slow, building energy through the middle, and ending calm. The camera is locked off.\n\nretention_analysis:\n<Subject 1> (appears in [Shot 1]): fully_preserved - her face, hairstyle, skin tone and figure identical to <Picture 1> throughout.\n\ndetailed_description:\nA realistic live-action dance video on a light gray-white studio backdrop with soft even lighting.\n[Shot 1] Locked-off static camera, medium full-body framing, no camera movement for the whole 10 seconds.\nFrom 00:00.000 to 00:00.500 the screen is pure black. At 00:00.500 the frame fades in revealing <Subject 1> centered, feet together, arms relaxed, chin lifted, ready as the music enters.\nFrom 00:00.500 to 00:03.000 she sways slowly to the beat: weight shifting side to side, shoulders rolling gently, hands tracing soft arcs, hips following the rhythm in smooth controlled motions.\nFrom 00:03.000 to 00:07.000 the tempo lifts: she steps side to side with light footwork, upper-body pops on the accents, arms swinging with more energy, a bright confident smile growing on her face, her hair moving naturally with each turn of her shoulders.\nFrom 00:07.000 to 00:09.500 she eases back into the slow sway of the opening, motion gradually settling, ending centered with a warm smile, her appearance exactly as in <Picture 1>.\nAt 00:09.500 the frame fades to black, fully black by 00:10.000.\n\noverall_soundscape:\nA clean bright studio room tone; soft footfalls on each step, light breaths on the accents, settling quiet through the fade-out.\n\nnon_diegetic_music:\nA smooth rhythmic pop track around 108 BPM with a steady four-on-the-floor kick, crisp claps and a warm bass line, accents matching the choreography changes at 00:03.000 and 00:07.000, ending on a soft sustained chord through the fade.",
        ],
        [
            'key'   => 'wave',
            'label' => 'Say hi',
            'desc'  => 'She waves and blows you a kiss',
            'prompt' => "subject_definitions:\n<Subject 1> is the young woman in <Picture 1>, with her facial features, hairstyle and skin tone exactly matching <Picture 1>, a natural figure, standing relaxed.\n\nsummary:\n[reference generation] A 10-second warm greeting clip. <Subject 1> from <Picture 1> notices the camera, smiles, waves hello and blows a kiss goodbye against a soft warm backdrop. The camera is locked off.\n\nretention_analysis:\n<Subject 1> (appears in [Shot 1]): fully_preserved - her face, hairstyle, skin tone and figure identical to <Picture 1> throughout.\n\ndetailed_description:\nA realistic live-action clip on a soft warm-toned backdrop with gentle flattering lighting.\n[Shot 1] Locked-off static camera, medium half-body framing, no camera movement.\nFrom 00:00.000 to 00:00.500 pure black. At 00:00.500 the frame fades in on <Subject 1> standing facing the camera, hands loosely clasped, a soft neutral expression.\nFrom 00:00.500 to 00:02.500 her eyes lift to the camera and a genuine smile blooms across her face; she tilts her head slightly, clearly delighted to see you.\nFrom 00:02.500 to 00:05.000 she raises her right hand and waves hello — a friendly side-to-side wave at chest height, her shoulders swaying lightly with the gesture, her smile widening.\nFrom 00:05.000 to 00:07.500 she brings her hand to her lips and blows a kiss toward the camera, fingers unfurling softly as the kiss flies, her eyes warm and playful.\nFrom 00:07.500 to 00:09.500 she lowers her hand, gives a small giggle and a last gentle wave, holding your gaze with a soft smile, her appearance exactly as in <Picture 1>.\nAt 00:09.500 the frame fades to black, fully black by 00:10.000.\n\noverall_soundscape:\nA quiet cozy room tone; the soft rustle of her clothing as she waves, a light warm giggle after the blown kiss, settling into silence through the fade.\n\nnon_diegetic_music:\nA tender lo-fi pop ballad around 80 BPM with soft piano, warm pads and a light snap beat, a gentle melodic lift right as she blows the kiss at 00:05.000, fading with a sustained piano note.",
        ],
        [
            'key'   => 'vlog',
            'label' => 'Daily vlog',
            'desc'  => 'A cozy morning moment at home',
            'prompt' => "subject_definitions:\n<Subject 1> is the young woman in <Picture 1>, with her facial features, hairstyle and skin tone exactly matching <Picture 1>, a natural figure.\n\nsummary:\n[reference generation] A 10-second cozy lifestyle vlog clip. <Subject 1> from <Picture 1> enjoys a slow morning at home by a sunlit window — stretching, sipping coffee, sharing a soft smile with the camera. The camera is locked off.\n\nretention_analysis:\n<Subject 1> (appears in [Shot 1]): fully_preserved - her face, hairstyle, skin tone and figure identical to <Picture 1> throughout.\n\ndetailed_description:\nA realistic live-action lifestyle clip in a cozy home interior with warm morning sunlight streaming through a window, sheer curtains, plants and soft textures in the background.\n[Shot 1] Locked-off static camera, medium framing, no camera movement.\nFrom 00:00.000 to 00:00.500 pure black. At 00:00.500 the frame fades in on <Subject 1> standing by the window in the sunlight, stretching her arms overhead with a contented sigh.\nFrom 00:00.500 to 00:03.000 she lowers her arms, brushes a strand of hair behind her ear and picks up a warm mug from the windowsill, cradling it in both hands.\nFrom 00:03.000 to 00:06.000 she takes a slow sip, eyes closing briefly in enjoyment, then glances toward the camera and smiles softly as if sharing the peaceful moment with you.\nFrom 00:06.000 to 00:09.500 she leans her shoulder against the window frame, mug in hand, looking out at the light then back to the camera with a relaxed happy expression, chatting silently, her appearance exactly as in <Picture 1>.\nAt 00:09.500 the frame fades to black, fully black by 00:10.000.\n\noverall_soundscape:\nA soft morning ambience: distant birdsong, the faint hum of the city, a gentle clink as the mug is set on the sill, a contented exhale after the sip.\n\nnon_diegetic_music:\nA warm acoustic lo-fi track around 85 BPM with a soft guitar loop, mellow keys and a light shaker, unhurried and cozy, ending on a gentle resolved chord through the fade.",
        ],
    ];

    protected array $middleware = [
        \app\live\middleware\Auth::class => ['only' => ['options', 'submit', 'myList']],
        \app\ai\middleware\AiAuth::class => ['only' => ['pending', 'accept', 'uploadVideo']],
    ];

    public function __construct(App $app)
    {
        parent::__construct($app);
    }

    /**
     * 可选角色 + 动作模板（登录用户：平台人设 + 我的角色）
     */
    public function options()
    {
        return $this->jsonSuccess([
            'presets' => self::PRESETS,
            'quota'   => ['daily_limit' => self::DAILY_LIMIT],
        ]);
    }

    /**
     * 提交生成任务（VIP 专属，multipart：image + preset + agreed_policy）
     */
    public function submit()
    {
        $userId = $this->getAuthUserId();
        $presetKey = (string) $this->request->post('preset', '');
        $agreed = (int) $this->request->post('agreed_policy', 0);

        if ($agreed !== 1) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'Please confirm the upload policy first.');
        }
        $preset = null;
        foreach (self::PRESETS as $p) {
            if ($p['key'] === $presetKey) { $preset = $p; break; }
        }
        if (!$preset) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'Please choose a style preset.');
        }

        // VIP 校验
        $vip = (new VipService())->status($userId);
        if (empty($vip['is_vip'])) {
            return $this->jsonFail(ResultCode::NO_PERMISSION, 'Custom video is a VIP feature. Please subscribe to VIP first.');
        }

        // 图片（≤10MB，jpg/png/webp；经 Upload 库自动压缩）
        $file = $this->request->file('image');
        if (!$file) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'Please upload an image.');
        }
        if ((int) $file->getSize() > 10 * 1024 * 1024) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'Image must be under 10MB.');
        }
        $ext = strtolower((string) $file->getOriginalExtension());
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'Only jpg / png / webp images are supported.');
        }
        $upload = new \app\common\library\Upload($file);
        $upload->setTopic('custom_video');
        $attachment = $upload->upload(null, 0, $userId);
        $imageUrl = (string) $attachment['url'];

        // 每日配额
        $todayCount = AiTask::where('source_type', 'custom_video')
            ->where('source_ref_id', $userId)
            ->whereLike('created_at', date('Y-m-d') . '%')
            ->count();
        if ($todayCount >= self::DAILY_LIMIT) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'Daily limit reached (' . self::DAILY_LIMIT . '/day). Try again tomorrow.');
        }

        // 建任务
        $task = new AiTask();
        $task->task_no     = StrHelper::orderNo('CV');
        $task->room_id     = 0;
        $task->task_type   = 'custom_video';
        $task->priority    = 5;
        $task->source_type = 'custom_video';
        $task->source_ref_id = $userId;
        $task->persona_id  = 0;
        $task->content     = json_encode([
            'action'    => $preset['key'],
            'label'     => $preset['label'],
            'prompt'    => $preset['prompt'],
            'image_url' => $imageUrl,
        ], JSON_UNESCAPED_UNICODE);
        $task->callback_mode = 'none';
        $task->status      = TaskStatus::PENDING->value;
        $task->created_at  = date('Y-m-d H:i:s');
        $task->updated_at  = date('Y-m-d H:i:s');
        $task->save();

        return $this->jsonSuccess([
            'task_id' => (int) $task->id,
            'task_no' => $task->task_no,
            'status'  => $task->status,
            'remain'  => max(0, self::DAILY_LIMIT - $todayCount - 1),
        ]);
    }

    /**
     * 我的任务列表
     */
    public function myList()
    {
        $userId = $this->getAuthUserId();
        $tasks = AiTask::where('source_type', 'custom_video')
            ->where('source_ref_id', $userId)
            ->order('id', 'desc')
            ->limit(50)
            ->select()->toArray();

        return $this->jsonSuccess(array_map(function (array $t) {
            $c = json_decode((string) $t['content'], true) ?: [];
            return [
                'task_id'    => (int) $t['id'],
                'task_no'    => $t['task_no'],
                'label'      => (string) ($c['label'] ?? ''),
                'action'     => (string) ($c['action'] ?? ''),
                'status'     => $t['status'],
                'video_url'  => $t['video_url'] ? $this->absUrl((string) $t['video_url']) : '',
                'cover_url'  => $t['cover_url'] ? $this->absUrl((string) $t['cover_url']) : '',
                'duration'   => (int) $t['duration_sec'],
                'created_at' => $t['created_at'],
                'finished_at' => $t['finished_at'],
            ];
        }, $tasks));
    }

    /**
     * worker：捞待处理任务（X-Api-Key 鉴权）
     */
    public function pending()
    {
        $count = min(3, max(1, (int) ($this->request->get('count') ?? 1)));
        $tasks = AiTask::where('task_type', 'custom_video')
            ->where('status', TaskStatus::PENDING->value)
            ->order('priority', 'desc')
            ->order('id', 'asc')
            ->limit($count)
            ->select()->toArray();

        return $this->jsonSuccess(array_map(function (array $t) {
            $c = json_decode((string) $t['content'], true) ?: [];
            return [
                'task_id'    => (int) $t['id'],
                'task_no'    => $t['task_no'],
                'action'     => (string) ($c['action'] ?? ''),
                'label'      => (string) ($c['label'] ?? ''),
                'prompt'     => (string) ($c['prompt'] ?? ''),
                'image_url'  => $this->absUrl((string) ($c['image_url'] ?? '')),
                'created_at' => $t['created_at'],
            ];
        }, $tasks));
    }

    /**
     * worker：认领任务（pending → accepted，记录 worker_id）
     */
    public function accept()
    {
        $taskId = (int) $this->request->post('task_id', 0);
        $workerId = (string) ($this->request->aiWorkerId ?? 'unknown');
        $task = AiTask::find($taskId);
        if (!$task || $task->task_type !== 'custom_video') {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'task 不存在');
        }
        if ($task->status !== TaskStatus::PENDING->value) {
            return $this->jsonFail(ResultCode::TASK_ALREADY_DONE, 'task 已被处理');
        }
        $now = date('Y-m-d H:i:s');
        $task->status = TaskStatus::ACCEPTED->value;
        $task->worker_id = $workerId;
        $task->accepted_at = $now;
        $task->updated_at = $now;
        $task->save();
        return $this->jsonSuccess(['task_id' => $taskId, 'status' => $task->status]);
    }

    /**
     * worker：上传成品视频（multipart file=video, task_id）
     */
    public function uploadVideo()
    {
        $taskId = (int) $this->request->post('task_id', 0);
        $file = $this->request->file('video');
        $cover = $this->request->file('cover');

        if ($taskId <= 0 || !$file) {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'task_id / video 必填');
        }
        $task = AiTask::find($taskId);
        if (!$task || $task->task_type !== 'custom_video') {
            return $this->jsonFail(ResultCode::PARAM_ERROR, 'task 不存在');
        }

        $upload = new \app\common\library\Upload($file);
        $upload->setTopic('custom_video');
        $attachment = $upload->upload(null, 0, 0);
        $videoUrl = (string) $attachment['url'];

        $coverUrl = '';
        if ($cover) {
            $upload2 = new \app\common\library\Upload($cover);
            $upload2->setTopic('custom_video');
            $coverUrl = (string) $upload2->upload(null, 0, 0)['url'];
        }

        // 视频文件不计入图片自动压缩（Upload 内部已按类型判断）
        $task->result_type = 'video_file';
        $task->cover_url = $coverUrl;
        $task->updated_at = date('Y-m-d H:i:s');
        $task->save();

        return $this->jsonSuccess(['video_url' => $videoUrl, 'cover_url' => $coverUrl]);
    }

    /** 统一返回相对路径（/storage/...）：同源访问正确，且不受 CDN 回源 Host 影响 */
    private function absUrl(string $url): string
    {
        if ($url === '' || str_starts_with($url, 'http')) {
            return $url;
        }
        return '/' . ltrim($url, '/');
    }
}
