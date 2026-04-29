<?php

declare(strict_types=1);

namespace Tiamenti\VkBotSdk\Enums;

/**
 * Типы вложений VK API.
 *
 * Значения совпадают со строками, которые VK ожидает
 * в параметре attachment метода messages.send.
 *
 * @see https://dev.vk.com/ru/reference/objects/attachments-message
 */
enum AttachmentType: string
{
    case Photo = 'photo';
    case Video = 'video';
    case Audio = 'audio';
    case Document = 'doc';
    case AudioMessage = 'audio_message';
    case Graffiti = 'graffiti';
    case Story = 'story';
    case Poll = 'poll';
    case Wall = 'wall';
    case Sticker = 'sticker';
    case Link = 'link';
    case Market = 'market';
    case MarketAlbum = 'market_album';
}
