<?php

declare(strict_types=1);

namespace Tiamenti\VkBotSdk\Enums;

/**
 * Типы событий VK Callback API / Long Poll API.
 *
 * @see https://dev.vk.com/ru/api/community-events/json-schema
 */
enum EventType: string
{
    // Сообщения
    case MessageNew     = 'message_new';
    case MessageReply   = 'message_reply';
    case MessageEdit    = 'message_edit';
    case MessageAllow   = 'message_allow';
    case MessageDeny    = 'message_deny';
    case MessageEvent   = 'message_event';
    case MessageRead    = 'message_read';
    case MessageTypingState = 'message_typing_state';

    // Реакции на фото
    case PhotoNew           = 'photo_new';
    case PhotoCommentNew    = 'photo_comment_new';
    case PhotoCommentEdit   = 'photo_comment_edit';
    case PhotoCommentDelete = 'photo_comment_delete';
    case PhotoCommentRestore = 'photo_comment_restore';

    // Реакции на аудио
    case AudioNew = 'audio_new';

    // Видео
    case VideoNew           = 'video_new';
    case VideoCommentNew    = 'video_comment_new';
    case VideoCommentEdit   = 'video_comment_edit';
    case VideoCommentDelete = 'video_comment_delete';
    case VideoCommentRestore = 'video_comment_restore';

    // Комментарии к записям
    case WallReplyNew     = 'wall_reply_new';
    case WallReplyEdit    = 'wall_reply_edit';
    case WallReplyDelete  = 'wall_reply_delete';
    case WallReplyRestore = 'wall_reply_restore';
    case WallPostNew      = 'wall_post_new';
    case WallRepost       = 'wall_repost';

    // Участники сообщества
    case GroupJoin            = 'group_join';
    case GroupLeave           = 'group_leave';
    case GroupChangeSettings  = 'group_change_settings';
    case GroupChangePhoto     = 'group_change_photo';
    case GroupOfficersEdit    = 'group_officers_edit';

    // Рынок
    case MarketCommentNew     = 'market_comment_new';
    case MarketCommentEdit    = 'market_comment_edit';
    case MarketCommentDelete  = 'market_comment_delete';
    case MarketCommentRestore = 'market_comment_restore';
    case MarketOrderNew       = 'market_order_new';
    case MarketOrderEdit      = 'market_order_edit';

    // Опросы
    case PollVoteNew = 'poll_vote_new';

    // Пользователи
    case UserBlock   = 'user_block';
    case UserUnblock = 'user_unblock';

    // Подтверждение Callback
    case Confirmation = 'confirmation';

    // Прочее
    case LeadFormsNew = 'lead_forms_new';
    case VkpayTransaction = 'vkpay_transaction';
    case AppPayload = 'app_payload';
    case LikeAdd    = 'like_add';
    case LikeRemove = 'like_remove';

    /**
     * Возвращает человекочитаемое описание события.
     */
    public function label(): string
    {
        return match ($this) {
            self::MessageNew     => 'Новое сообщение',
            self::MessageReply   => 'Ответ на сообщение',
            self::MessageEdit    => 'Редактирование сообщения',
            self::MessageAllow   => 'Разрешение уведомлений',
            self::MessageDeny    => 'Запрет уведомлений',
            self::MessageEvent   => 'Событие кнопки (callback)',
            self::GroupJoin      => 'Вступление в сообщество',
            self::GroupLeave     => 'Выход из сообщества',
            self::Confirmation   => 'Подтверждение Callback API',
            default              => $this->value,
        };
    }

    /**
     * Является ли событие входящим сообщением.
     */
    public function isMessage(): bool
    {
        return in_array($this, [
            self::MessageNew,
            self::MessageReply,
            self::MessageEdit,
        ], strict: true);
    }
}
