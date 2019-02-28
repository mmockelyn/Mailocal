<?php
/**
 * Parser.php
 *
 * Created By: jonathan
 * Date: 20/02/2019
 * Time: 13:45
 */
namespace App\Email;

use MS\Email\Parser\Attachment;
use MS\Email\Parser\Parser as BaseParser;
use Html2Text\Html2Text;
use MS\Email\Parser\Part;

class Parser extends BaseParser
{
    protected function getHtml(){
        if(!is_array($this->parts['body'])){
            if (preg_match('/(\<html|\<body)/', $this->parts['body'])) {
                return quoted_printable_decode($this->parts['body']);
            }
            return false;
        }
        if($r = $this->searchByHeader('/content\-type/','/text\/html/')) {
            return $r[0]->getDecodedContent();
        }

        return false;
    }

    protected function getText(){
        if(!is_array($this->parts['body'])){
            if (preg_match('/(\<html|\<body)/', $this->parts['body'])) {
                return false;
            }
            return $this->parts['body'];
        }
        if($r = $this->searchByHeader('/content\-type/','/text\/plain/')) {
            return $r[0]->getDecodedContent();
        }

        return false;
    }

    protected function getTextContent($html)
    {
        $txt = new Html2Text(quoted_printable_decode($html));
        return preg_replace('/^[\s]+/m', '',
            trim($txt->getText(),  " \t\n\r ".urldecode("%C2%A0")));
    }


    protected function getAttachments(){
        if(!is_array($this->parts['body'])) return false;
        $attachments = $this->searchByHeader('/content\-disposition/','/attachment/');
        $attachments = $attachments ? $attachments : array();

        $attachmentObjects = array();
        foreach($attachments as $attachment){
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