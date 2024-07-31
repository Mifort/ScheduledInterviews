<?php

namespace api\macModels\forms\reports;

use api\hhModels\interview\Interview;
use api\hhModels\offer\Offer;
use api\hhModels\user\User;
use api\hhModels\vacancy\Vacancy;
use api\macModels\interviewStatus\InterviewStatus;
use api\models\partner\Partner;
use api\models\partnerVirtualHr\PartnerVirtualHr;
use common\helpers\ArrayHelper;
use common\macModels\expressClient\ExpressClient;
use yii;
use yii\db\Expression;
use yii\helpers\Html;

class ScheduledInterviewsForm extends BaseReport
{
    private const IDX_PARTNER = 0;
    private const IDX_STATUS = 1;
    private const IDX_INTERVIEW_DATE = 2;
    private const IDX_INTERVIEW_TIME = 3;
    private const IDX_FIO_CANDIDATE = 4;
    private const IDX_PHONE_CANDIDATE = 5;
    private const IDX_RESUME_CANDIDATE = 6;

    public function getReportResponse(ModelResponseJob $job)
    {
        $headers = [
            self::IDX_PARTNER          => 'Клиент',
            self::IDX_STATUS           => 'Статус',
            self::IDX_INTERVIEW_DATE   => 'Дата собеседования',
            self::IDX_INTERVIEW_TIME   => 'Время собеседования',
            self::IDX_FIO_CANDIDATE    => 'ФИО',
            self::IDX_PHONE_CANDIDATE  => 'Телефон',
            self::IDX_RESUME_CANDIDATE => 'Резюме кандидата',
        ];
        $footers = [
            self::IDX_PARTNER          => 'Итого',
            self::IDX_STATUS           => '',
            self::IDX_INTERVIEW_DATE   => '',
            self::IDX_INTERVIEW_TIME   => '',
            self::IDX_FIO_CANDIDATE    => '',
            self::IDX_PHONE_CANDIDATE  => '',
            self::IDX_RESUME_CANDIDATE => '',
        ];
        $params = [
            'common_styles' => $this->commonStyles,
            'custom_styles' => [],
        ];

        $interviewsQuery = Interview::find()
            ->alias('i')
            ->select(
                [
                    'interview_date'    => 'i.date',
                    'interview_time'    => 'i.time',
                    'mac_partner_id'    => 'v.mac_partner_id',
                    'status_name'       => 'ist.name',
                    'status_color'      => 'ist.color',
                    'candidate_id'      => 'o.user_id',
                    'candidate_phone'   => 'u.phone_number',
                    'candidate_fio'     => "CONCAT_WS(' ', u.last_name, u.first_name, u.patronymic)",
                ]
            )
            ->innerJoin(['ist' => InterviewStatus::tableName()], 'i.status_id = ist.id')
            ->innerJoin(['o' => Offer::tableName()], 'i.offer_id = o.id')
            ->innerJoin(['u' => User::tableName()], 'u.id = o.user_id')
            ->innerJoin(['v' => Vacancy::tableName()], 'o.vacancy_id = v.id')
            ->where(
                [
                    'u.test' => false
                ]
            );
        $this->applyDateFilter($interviewsQuery, 'i.date');
        $partnerVrQuery = PartnerVirtualHr::find()
            ->alias('pv')
            ->select(
                [
                    'vr_partner_id'    => 'pv.partner_id',
                    'vr_partner_title' => 'p.title',
                ]
            )
            ->innerJoin(['p' => Partner::tableName()], 'p.id = pv.partner_id')
            ->where(
                [
                    'pv.enabled' => true,
                    'p.enabled'  => true,
                    'p.test'     => false
                ]
            )
            ->andWhere([
                'OR',
                ['pv.expired_at' => null],
                [
                    '>=',
                    'pv.expired_at',
                    new Expression('CURRENT_TIMESTAMP')
                ]
            ]);
        $partnersVr = $partnerVrQuery->asArray()->all();

        $data = [];
        foreach ($partnersVr as $dataPartner) {
            foreach ($interviewsQuery->asArray()->all() as $dataInterview) {
                if ($dataPartner['vr_partner_id'] === $dataInterview['mac_partner_id']) {
                    $user = User::find()->where(['id' => (int)$dataInterview['candidate_id']])->one();
                    $resumeDownloadLink = $user->getResumeDownloadLink();
                    $data[] = [
                        self::IDX_PARTNER          => $dataPartner['vr_partner_title'],
                        self::IDX_STATUS           => $dataInterview['status_name'],
                        self::IDX_INTERVIEW_DATE   => $dataInterview['interview_date'],
                        self::IDX_INTERVIEW_TIME   => $dataInterview['interview_time'],
                        self::IDX_FIO_CANDIDATE    => $dataInterview['candidate_fio'],
                        self::IDX_PHONE_CANDIDATE  => $dataInterview['candidate_phone'],
                        // Здесь формируется ссылка резюме для web-страницы.
                        // Для excel-файла используется другая логика $params['links']
                        self::IDX_RESUME_CANDIDATE => Html::a('Скачать', [$resumeDownloadLink]),
                    ];
                    $params['links'][] = [
                        'date'         => $dataInterview['interview_date'],
                        'time'         => $dataInterview['interview_time'],
                        'numberLetter' => self::IDX_RESUME_CANDIDATE + 1,
                        'link'         => $resumeDownloadLink,
                        'linkText'     => 'Скачать'
                    ];
                    $params['custom_styles']['cells'][] = [
                        self::IDX_STATUS => ['background-color' => $dataInterview['status_color']]
                    ];
                }
            }
        }
        ArrayHelper::multisort($data, [2, 3], [SORT_ASC, SORT_ASC]);
        ArrayHelper::multisort($params['links'], ['date', 'time'], [SORT_ASC, SORT_ASC]);

        $params['headers'] = $headers;
        $params['footers'] = array_values($footers);

        return ['params' => $params, 'data' => array_values($data)];
    }
}
