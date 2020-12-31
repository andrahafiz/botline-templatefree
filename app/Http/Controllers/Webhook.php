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

            // create welcome message
            $message  = "Salam kenal, " . $profile['displayName'] . "!\n";
            $message .= "Silakan kirim pesan \"MULAI\" untuk memulai kuis Tebak Kode.";
            $textMessageBuilder = new TextMessageBuilder($message);

            // create sticker message
            $stickerMessageBuilder = new StickerMessageBuilder(1, 3);

            // merge all message
            $multiMessageBuilder = new MultiMessageBuilder();
            $multiMessageBuilder->add($textMessageBuilder);
            $multiMessageBuilder->add($stickerMessageBuilder);

            // send reply message
            $this->bot->replyMessage($event['replyToken'], $multiMessageBuilder);

            // save user data
            $this->userGateway->saveUser(
                $profile['userId'],
                $profile['displayName']
            );
        }
    }

    private function textMessage($event)
    {
        $userMessage = $event['message']['text'];
        if (strtolower($userMessage) == 'template admin') {
            // $this->sendQuestion($event['replyToken']);
            $this->sendQuestion($event['replyToken']);
        } else {
            $message = 'Sepertinya kamu mengetikan perintah yang tidak ada.';
            $textMessageBuilder = new TextMessageBuilder($message);

            $message2 = "Berikut beberapa pilihan perintah yang tersedia " . "!\n" . "1. Template Admin " . "!\n" . "2. Template Lainnya";
            $textMessageBuilder2 = new TextMessageBuilder($message2);

            $stickerMessageBuilder = new StickerMessageBuilder(1, 10);
            $multiMessageBuilder = new MultiMessageBuilder();
            $multiMessageBuilder->add($textMessageBuilder);
            $multiMessageBuilder->add($stickerMessageBuilder);
            $multiMessageBuilder->add($textMessageBuilder2);

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

        // '';
        // foreach ($data["\u0000*\u0000items"] as $d) {
        //     $sting .= $d['rating'] . ',';
        // }
        $textMessageBuilder = new TextMessageBuilder($text);
        $this->bot->replyMessage($replyToken, $textMessageBuilder);
    }

    private function sendQuestion($replyToken)
    {
        //   // get question from database
        //   $question = $this->TemplateGateway->getData($questionNum);

        //   // prepare answer options
        //   for ($opsi = "a"; $opsi <= "d"; $opsi++) {


        $data = $this->templateGateway->getData();
        $sting = json_encode($data);
        $red = json_decode($sting, true);
        $hero_image = "";
        foreach ($red as $value) {
            $hero_image = $value['image'];
        }

        $builder = new CarouselContainerBuilder([
            BubbleContainerBuilder::builder()
                ->setDirection("ltr")
                ->setHero(
                    // new ImageComponentBuilder('https://d17ivq9b7rppb3.cloudfront.net/original/commons/home-hero-new.jpg')
                    ImageComponentBuilder::builder()
                        ->setUrl($hero_image)
                        ->setSize("full")
                        ->setAspectRatio("320:213")
                        ->setAspectMode("cover")
                )->setBody(
                    BoxComponentBuilder::builder()
                        ->setLayout("vertical")
                        ->setSpacing("sm")
                        ->setPaddingAll("13px")
                        ->setContents(
                            [
                                new TextComponentBuilder("judul", null, null, "sm", null, null, true, null, 'bold'),
                                new BoxComponentBuilder(
                                    'baseline',
                                    [
                                        new IconComponentBuilder('https://scdn.line-apps.com/n/channel_devcenter/img/fx/review_gold_star_28.png', null, "xs"),
                                        new IconComponentBuilder('https://scdn.line-apps.com/n/channel_devcenter/img/fx/review_gray_star_28.png', null, "xs"),
                                        new IconComponentBuilder('https://scdn.line-apps.com/n/channel_devcenter/img/fx/review_gray_star_28.png', null, "xs"),
                                        new IconComponentBuilder('https://scdn.line-apps.com/n/channel_devcenter/img/fx/review_gray_star_28.png', null, "xs"),
                                        new IconComponentBuilder('https://scdn.line-apps.com/n/channel_devcenter/img/fx/review_gray_star_28.png', null, "xs"),
                                        new TextComponentBuilder('4.0', null, "md", "xs", null, null, null, null, null, "#8c8c8c")
                                    ]
                                ),
                                new BoxComponentBuilder(
                                    'vertical',
                                    [
                                        new BoxComponentBuilder(
                                            'baseline',
                                            [new TextComponentBuilder('Keterangan', 5, null, "xs", null, null, null, null, null, "#000000")],
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
                                new ButtonComponentBuilder(new UriTemplateActionBuilder('Priview', 'https://www.dicoding.com/'), null, null, null, "secondary"),
                                new ButtonComponentBuilder(new UriTemplateActionBuilder('Download', 'https://www.dicoding.com/'), null, null, null, "primary")
                            ]
                        )
                ),
            BubbleContainerBuilder::builder()->setBody(
                new BoxComponentBuilder(ComponentLayout::VERTICAL, [new TextComponentBuilder('World!')])
            )
        ]);

        // build message
        $messageBuilder = new FlexMessageBuilder("Gunakan mobile app untuk melihat soal", $builder);

        // send message
        $response = $this->bot->replyMessage($replyToken, $messageBuilder);
    }
}
