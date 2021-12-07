<?php declare(strict_types=1);
/* Copyright (c) 1998-2019 ILIAS open source, Extended GPL, see docs/LICENSE */

namespace ILIAS\OnScreenChat\Provider;

use ilDatePresentation;
use ilDateTime;
use ilDateTimeException;
use ILIAS\DI\Container;
use ILIAS\GlobalScreen\Scope\MainMenu\Provider\AbstractStaticMainMenuProvider;
use ILIAS\MainMenu\Provider\StandardTopItemsProvider;
use ILIAS\OnScreenChat\Repository\Conversation;
use ILIAS\OnScreenChat\Repository\Subscriber;
use ILIAS\UI\Component\Symbol\Icon\Standard;
use ILIAS\UI\Implementation\Component\Item\Shy;
use ilSetting;
use ilUserUtil;
use JsonException;

/**
 * Class OnScreenChatProvider
 * @author Ingmar Szmais <iszmais@databay.de>
 */
class OnScreenChatProvider extends AbstractStaticMainMenuProvider
{
    private Conversation $conversationRepo;
    private Subscriber $subscriberRepo;

    public function __construct(
        Container $dic,
        ?Conversation $conversationRepo = null,
        ?Subscriber $subscriberRepo = null
    ) {
        parent::__construct($dic);
        $dic->language()->loadLanguageModule('chatroom');
        $this->conversationRepo = $conversationRepo ?? new Conversation($this->dic->database(), $this->dic->user());
        $this->subscriberRepo = $subscriberRepo ?? new Subscriber($this->dic->database(), $this->dic->user());
    }

    /**
     * @inheritDoc
     */
    public function getStaticTopItems() : array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getStaticSubItems() : array
    {
        $icon = $this->dic->ui()->factory()->symbol()->icon()->standard(
            Standard::CHTA,
            $this->dic->language()->txt('public_room')
        )->withIsOutlined(true);

        return [
            $this->mainmenu->complex($this->if->identifier('mm_chat'))
                ->withAvailableCallable(function () {
                    $isUser = 0 !== (int) $this->dic->user()->getId() && !$this->dic->user()->isAnonymous();
                    $chatSettings = new ilSetting('chatroom');
                    $isEnabled = $chatSettings->get('chat_enabled') && $chatSettings->get('enable_osc');
                    return $isUser && $isEnabled;
                })
                ->withTitle($this->dic->language()->txt('obj_chtr'))
                ->withSymbol($icon)
                ->withContent($this->dic->ui()->factory()->item()->shy('')->withAdditionalOnLoadCode(
                    function ($id) {
                        return "il.OnScreenChat.menuCollector = $id.parentNode;$id.remove();";
                    })
                )
                ->withParent(StandardTopItemsProvider::getInstance()->getCommunicationIdentification())
                ->withPosition(25)
            ,
        ];
    }


    /**
     * @param string $conversationIds
     * @param bool   $withAggregates
     *
     * @return Shy[]
     * @throws JsonException
     * @throws ilDateTimeException
     */
    public function getAsyncItem(string $conversationIds, bool $withAggregates) : array
    {
        $conversationIds = array_filter(explode(',', $conversationIds));

        if (!$withAggregates || !$this->dic->user()->getId() || $this->dic->user()->isAnonymous()) {
            return [];
        }

        $items = [];
        foreach ($this->conversationRepo->findByIds($conversationIds) as $conversation) {
            if ($conversation->isGroup()) {
                $icon = $this->dic->ui()->factory()->symbol()->icon()->standard(Standard::GCON, 'group-conversation');
            } else {
                $icon = $this->dic->ui()->factory()->symbol()->icon()->standard(Standard::CON, 'conversation');
            }

            $usernames = [];
            foreach ($conversation->getSubscriberUsrIds() as $id) {
                $usernames[] = ilUserUtil::getNamePresentation($id);
            }

            $items[] = $this->dic->ui()->factory()->item()->shy(implode(', ', $usernames))
                  ->withDescription($conversation->getLastMessage()->getMessage())
                  ->withProperties(
                      [$this->dic->language()->txt('time') . ':' =>
                          ilDatePresentation::formatDate(
                              new ilDateTime(
                                  (int) ($conversation->getLastMessage()->getCreatedTimestamp() / 1000),
                                  IL_CAL_UNIX
                              )
                          )
                      ])
                  ->withLeadIcon($icon->withIsOutlined(true))
                  ->withClose($this->dic->ui()->factory()->button()->close());
        }

        return $items;
    }
}
