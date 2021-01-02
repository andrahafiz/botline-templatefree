<?php

namespace App\Http\Controllers;

use App\Gateway\EventLogGateway;
use App\Gateway\TemplateGateway;
use App\Gateway\UserGateway;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Log\Logger;
use LINE\LINEBot;
use LINE\LINEBot\HTTPClient\CurlHTTPClient;
use LINE\LINEBot\MessageBuilder\MultiMessageBuilder;
use LINE\LINEBot\MessageBuilder\StickerMessageBuilder;
use LINE\LINEBot\MessageBuilder\TemplateBuilder\ButtonTemplateBuilder;
use LINE\LINEBot\MessageBuilder\TemplateBuilder\CarouselColumnTemplateBuilder;
use LINE\LINEBot\MessageBuilder\TemplateBuilder\CarouselTemplateBuilder;
use LINE\LINEBot\MessageBuilder\TemplateMessageBuilder;
use LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder;
use LINE\LINEBot\TemplateActionBuilder\UriTemplateActionBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ContainerBuilder\CarouselContainerBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ContainerBuilder\BubbleContainerBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\BoxComponentBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ImageComponentBuilder;
use LINE\LINEBot\Constant\Flex\ComponentLayout;
use LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ButtonComponentBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\IconComponentBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder;
use LINE\LINEBot\MessageBuilder\FlexMessageBuilder;

class Webhook extends Controller
{
    /**
     * @var LINEBot
     */
    private $bot;
    /**
     * @var Request
     */
    private $request;
    /**
     * @var Response
     */
    private $response;
    /**
     * @var Logger
     */
    private $logger;
    /**
     * @var EventLogGateway
     */
    private $logGateway;
    /**
     * @var UserGateway
     */
    private $userGateway;
    /**
     * @var TemplateGateway
     */
    private $templateGateway;
    /**
     * @var array
     */
    private $user;


    public function __construct(
        Request $request,
        Response $response,
        Logger $logger,
        // EventLogGateway $logGateway,
        UserGateway $userGateway,
        TemplateGateway $templateGateway
    ) {
        $this->request = $request;
        $this->response = $response;
        $this->logger = $logger;
        // $this->logGateway = $logGateway;
        $this->userGateway = $userGateway;
        $this->templateGateway = $templateGateway;
        // $this->questionGateway = $questionGateway;

        // create bot object
        $httpClient = new CurlHTTPClient(getenv('CHANNEL_ACCESS_TOKEN'));
        $this->bot  = new LINEBot($httpClient, ['channelSecret' => getenv('CHANNEL_SECRET')]);
    }

    public function __invoke()
    {
        // get request
        $body = $this->request->all();

        // debuging data
        $this->logger->debug('Body', $body);

        // save log
        $signature = $this->request->server('HTTP_X_LINE_SIGNATURE') ?: '-';

        return $this->handleEvents();
    }

    private function handleEvents()
    {
        $data = $this->request->all();

        if (is_array($data['events'])) {
            foreach ($data['events'] as $event) {
                // skip group and room event
                if (!isset($event['source']['userId'])) continue;

                // get user data from database
                $this->user = $this->userGateway->getUser($event['source']['userId']);

                // if user not registered
                if (!$this->user) $this->followCallback($event);
                else {
                    // respond event
                    if ($event['type'] == 'message') {
                        if (method_exists($this, $event['message']['type'] . 'Message')) {
                            $this->{$event['message']['type'] . 'Message'}($event);
                        }
                    } else {
                        if (method_exists($this, $event['type'] . 'Callback')) {
                            $this->{$event['type'] . 'Callback'}($event);
                        }
                    }
                }
            }
        }
        $this->response->setContent("No events found!");
        $this->response->setStatusCode(200);
        return $this->response;
    }



    private function followCallback($event)
    {
        $res = $this->bot->getProfile($event['source']['userId']);
        if ($res->isSucceeded()) {
            $profile = $res->getJSONDecodedBody();
            $message  = "Halo " . $profile['displayName'] . "!\n";
            $message .= "Terima kasih telah menambahkan saya sebagai teman anda";
            $textMessageBuilder = new TextMessageBuilder($message);

            $stickerMessageBuilder = new StickerMessageBuilder(1, 2);

            $message2 = "Kegunaan akun ini adalah menampilkan beberapa rekomendasi template website yang dapat anda download secara gratis..semoga semakin semangat desiginnya";
            $textMessageBuilder2 = new TextMessageBuilder($message2);

            $message3 = "Bagaimana cara menggunakan nya ? untuk mencobanya silahkan pilih pilihan yang telah kami sediakan";
            $textMessageBuilder3 = new TextMessageBuilder($message3);

            $textMessageBuilder4 =  new TemplateMessageBuilder(
                "Pilihan",
                new ButtonTemplateBuilder(
                    null,
                    "Pilihan : ",
                    null,
                    [
                        new MessageTemplateActionBuilder('Template Admin', 'Template Admin'),
                        new MessageTemplateActionBuilder('Template Lainnya', 'Template Lainnya')
                    ]
                )
            );

            $multiMessageBuilder = new MultiMessageBuilder();
            $multiMessageBuilder->add($textMessageBuilder);
            $multiMessageBuilder->add($stickerMessageBuilder);
            $multiMessageBuilder->add($textMessageBuilder2);
            $multiMessageBuilder->add($textMessageBuilder3);
            $multiMessageBuilder->add($textMessageBuilder4);

            $this->bot->replyMessage($event['replyToken'], $multiMessageBuilder);

            $this->userGateway->saveUser(
                $profile['userId'],
                $profile['displayName']
            );
        }
    }
    //IMAGE BAGUS https://user-images.githubusercontent.com/10515204/56117400-9a911800-5f85-11e9-878b-3f998609a6c8.jpg

    private function textMessage($event)
    {
        $userMessage = $event['message']['text'];
        if (strtolower($userMessage) == 'template admin' or strtolower($userMessage) == 'template lainnya') {
            $this->sendQuestion($event['replyToken'], $userMessage);
        } else {
            $message = 'Sepertinya kamu mengetikan perintah yang tidak tersedia.';
            $textMessageBuilder = new TextMessageBuilder($message);

            // $message2 = "Berikut beberapa pilihan perintah yang tersedia " . "!\n" . "1. Template Admin " . "!\n" . "2. Template Lainnya";
            // $textMessageBuilder2 = new TextMessageBuilder($message2);

            $textMessageBuilder4 =  new TemplateMessageBuilder(
                "Rekomendasi pilihan",
                new ButtonTemplateBuilder(
                    null,
                    "Berikut beberapa pilihan perintah yang kami sediakan : ",
                    null,
                    [new MessageTemplateActionBuilder('Template Admin', 'Template Admin'), new MessageTemplateActionBuilder('Template Lainnya', 'Template Lainnya')]
                )
            );

            $stickerMessageBuilder = new StickerMessageBuilder(1, 10);
            $multiMessageBuilder = new MultiMessageBuilder();
            $multiMessageBuilder->add($textMessageBuilder);
            $multiMessageBuilder->add($stickerMessageBuilder);
            $multiMessageBuilder->add($textMessageBuilder4);

            $this->bot->replyMessage($event['replyToken'], $multiMessageBuilder);
        };
    }


    private function test($replyToken)
    {
        $data = $this->templateGateway->getData();
        // file_put_contents('php://stderr', 'Data: ' . json_encode($data));
        $sting = json_encode($data);
        $red = json_decode($sting, true);
        $text = "";
        foreach ($red as $value) {
            $text .= $value['id'];
        }

        $textMessageBuilder = new TextMessageBuilder($text);
        $this->bot->replyMessage($replyToken, $textMessageBuilder);
    }

    private function sendQuestion($replyToken, $keyword)
    {
        $data = $this->templateGateway->getData($keyword);
        $converttojson = json_encode($data);
        $converttoarray = json_decode($converttojson, true);
        // $hero_image = "";

        //Create List Template
        $columns = array();
        $multiMessageBuilder = new MultiMessageBuilder();
        if (isset($converttoarray)) {
            //jika  data ada
            foreach ($converttoarray as $value) {
                # code...
                $icon = array();
                for ($i = 1; $i <= 5; $i++) {
                    if ($i <= $value['rating']) {
                        $icon[] =  new IconComponentBuilder('https://scdn.line-apps.com/n/channel_devcenter/img/fx/review_gold_star_28.png', null, "xs");
                    } else {
                        $icon[] =  new IconComponentBuilder('https://scdn.line-apps.com/n/channel_devcenter/img/fx/review_gray_star_28.png', null, "xs");
                    }
                }
                $icon[] = new TextComponentBuilder(floatval($value['rating']) . "", null, "md", "xs", null, null, null, null, null, "#8c8c8c");
                $columns[] = BubbleContainerBuilder::builder()
                    ->setDirection("ltr")
                    ->setHero(
                        new ImageComponentBuilder($value['image'], null, null, null, null, "full", "320:213", "cover")
                    )->setBody(
                        // new BoxComponentBuilder("vertical",)
                        BoxComponentBuilder::builder()
                            ->setLayout("vertical")
                            ->setSpacing("sm")
                            ->setPaddingAll("13px")
                            ->setContents(
                                [
                                    new TextComponentBuilder($value['judul_template'], null, null, "lg", null, null, true, null, 'bold', "#000000"),
                                    new BoxComponentBuilder(
                                        'baseline',
                                        $icon
                                    ),
                                    new BoxComponentBuilder(
                                        'vertical',
                                        [
                                            new BoxComponentBuilder(
                                                'baseline',
                                                [new TextComponentBuilder("Keterangan :", 5, null, "md", null, null, true, null, "bold", "#000000")],
                                                null,
                                                "sm"
                                            ),
                                            new BoxComponentBuilder(
                                                'baseline',
                                                [new TextComponentBuilder($value['keterangan'], 5, null, "xs", null, null, true, null, null, "#696d65")],
                                                null,
                                                "sm"
                                            )
                                        ]
                                    )
                                ]
                            )

                    )->setFooter(
                        BoxComponentBuilder::builder()
                            ->setLayout("horizontal")
                            ->setSpacing("sm")
                            ->setContents(
                                [
                                    new ButtonComponentBuilder(new UriTemplateActionBuilder('Priview', $value['link_prev']), null, null, null, "secondary"),
                                    new ButtonComponentBuilder(new UriTemplateActionBuilder('Download', $value['link_down']), null, null, null, "primary")
                                ]
                            )
                    );
            }

            $builder = new CarouselContainerBuilder($columns);

            //Show List Template
            $messageBuilder = new FlexMessageBuilder("Gunakan mobile app untuk melihat soal", $builder);

            //Pesan rekomendasi pilihan
            $rekomendasiopsi =  new TemplateMessageBuilder(
                "Rekomendasi pilihan",
                new ButtonTemplateBuilder(
                    null,
                    "Berikut beberapa pilihan perintah yang kami sediakan : ",
                    null,
                    [new MessageTemplateActionBuilder('Template Admin', 'Template Admin'), new MessageTemplateActionBuilder('Template Lainnya', 'Template Lainnya')]
                )
            );
            $multiMessageBuilder->add($messageBuilder);
            $multiMessageBuilder->add($rekomendasiopsi);
        } else {
            $message = 'Maaf, sepertinya data yang kamu inginkan tidak ada';
            $textMessageBuilder = new TextMessageBuilder($message);

            $textMessageBuilder2 =  new TemplateMessageBuilder(
                "Rekomendasi pilihan",
                new ButtonTemplateBuilder(
                    null,
                    "Mungkin coba lagi dengan opsi yang telah kami sediakan ",
                    null,
                    [new MessageTemplateActionBuilder('Template Admin', 'Template Admin'), new MessageTemplateActionBuilder('Template Lainnya', 'Template Lainnya')]
                )
            );

            $stickerMessageBuilder = new StickerMessageBuilder(1, 111);
            $multiMessageBuilder = new MultiMessageBuilder();
            $multiMessageBuilder->add($textMessageBuilder);
            $multiMessageBuilder->add($stickerMessageBuilder);
            $multiMessageBuilder->add($textMessageBuilder2);
        }



        // send message
        $response = $this->bot->replyMessage($replyToken, $multiMessageBuilder);
    }
}
