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