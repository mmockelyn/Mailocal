<?php

/*
 * This file is part of the Maillocal package.
 *
 * Copyright 2019 Jonathan Foucher
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 * @package Mailocal
 */

namespace App\Email;

use MS\Email\Parser\Attachment;
use MS\Email\Parser\Parser as BaseParser;
use Html2Text\Html2Text;
use MS\Email\Parser\Part;

class Parser extends BaseParser
{
    protected function getHtml()
    {
        if (!is_array($this->parts['body'])) {
            if (preg_match('/(\<html|\<body)/', $this->parts['body'])) {
                return quoted_printable_decode($this->parts['body']);
            }
            return false;
        }
        if ($r = $this->searchByHeader('/content\-type/', '/text\/html/')) {
            return $r[0]->getDecodedContent();
        }

        return false;
    }

    protected function getText()
    {
        if (!is_array($this->parts['body'])) {
            if (preg_match('/(\<html|\<body)/', $this->parts['body'])) {
                return false;
            }
            return $this->parts['body'];
        }
        if ($r = $this->searchByHeader('/content\-type/', '/text\/plain/')) {
            return $r[0]->getDecodedContent();
        }

        return false;
    }

    protected function getTextContent($html)
    {
        $txt = new Html2Text(quoted_printable_decode($html));
        return preg_replace(
            '/^[\s]+/m',
            '',
            trim($txt->getText(), " \t\n\r ".urldecode("%C2%A0"))
        );
    }


    protected function getAttachments()
    {
        if (!is_array($this->parts['body'])) {
            return false;
        }
        $attachments = $this->searchByHeader('/content\-disposition/', '/attachment/');
        $attachments = $attachments ? $attachments : array();

        $attachmentObjects = array();
        foreach ($attachments as $attachment) {
            /** @var Part $attachment */
            $matches = array();
            preg_match('/filename=([^;]*)/', $attachment->getDisposition(), $matches);
            if (!isset($matches[1])) {
                // No match, try with utf-8 thing
                preg_match('/filename\*=utf-8\'\'([^;]*)/', $attachment->getDisposition(), $matches);
                if (!isset($matches[1])) {
                    throw new InvalidAttachmentException('Attachement is invalid ' . $attachment->getDisposition());
                }
            }
            $filename = trim($matches[1], "\" \t\r\n\0\x0B");

            $matches = array();
            preg_match('/([^;]*)/', $attachment->getType(), $matches);
            $mimeType = $matches[1];

            $attachmentObjects[] = new Attachment($filename, $attachment->getDecodedContent(), $mimeType);
        }

        return $attachmentObjects;
    }
}
