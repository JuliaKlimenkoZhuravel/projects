<?php

namespace Application\Service;

use Zend\ServiceManager\ServiceLocatorAwareInterface;

class PushNotificationService implements ServiceLocatorAwareInterface
{
    use \Zend\ServiceManager\ServiceLocatorAwareTrait;

    public function saveRegistrationId($regId)
    {
        $entityManager = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');
        /** @var \Application\Repository\PushNotificationGoogleRepository $subscribersRepository */
        $subscribersRepository = $entityManager->getRepository('\Application\Entity\PushNotificationGoogle');

        //save registration id for push notifications
        $registration_id = str_replace("https://android.googleapis.com/gcm/send/", "", $regId);
        if ($registration_id) {
            $subscribersTmp = $subscribersRepository->findOneBy(array('registration_id' => $registration_id));
            if (!$subscribersTmp) {
                $subscribers = new \Application\Entity\PushNotificationGoogle();
                $subscribers->setRegistrationId($registration_id);
                $entityManager->persist($subscribers);
                $entityManager->flush();
            }
        }
        return false;
    }

    public function pushNotification($registration_ids)
    {
        $entityManager = $this->getServiceLocator()->get('Doctrine\ORM\EntityManager');
        /** @var \Application\Repository\ArticleRepository $articleRepository */
        $articleRepository = $entityManager->getRepository('\Application\Entity\Article');

        $article = $articleRepository->findOneById($registration_ids);
        if (isset($article) && $article->getPushIsSent() == 0 && $article->getPushReady() == 1) {
            //push notification
            $point = mb_strpos($article->getDescription(), '.');
            $counter = 0;
            $pos = 0;
            $str = $article->getDescription();
            while ($counter < 11) {
                $posTmp = mb_strpos($str, " ", $pos + 1);
                if ($posTmp === false) {
                    break;
                }
                $pos = $posTmp;
                $counter++;
            }
            $article_notification['title'] = $article->getTitle();
            $article_notification['url'] = $article->getUrl() . '?push_read=1';
            $article_notification['body'] = mb_substr($article->getDescription(), 0, min($point, $pos)) . '...';
            $message = json_encode(array('notification' => $article_notification));
            file_put_contents('public/js/message.json', $message);
            $subscribersRepository = $entityManager->getRepository('\Application\Entity\PushNotificationGoogle');
            $subscribers = $subscribersRepository->findAll();

            if (!empty($subscribers)) {
                $subscribersCount = count($subscribers);
                $counter = 0;
                $reg_ids = array();
                foreach ($subscribers as $subscriber) {
                    $reg_ids[] = $subscriber->getRegistrationId();
                    ++$counter;
                    if (count($reg_ids) == 999 && $counter != $subscribersCount) {
                        $this->sendGcmNotification($reg_ids, $message);
                        $reg_ids = array();
                    }
                }
                $this->sendGcmNotification($reg_ids, $message);
            }
            $article->setPushIsSent(1);
            $entityManager->persist($article);
            $entityManager->flush();
        }
        return false;
    }

    public function sendGcmNotification($reg_ids, $message)
    {
        $config = $this->getServiceLocator()->get('Configuration');
        $headers = array(
            'Authorization: key=' . $config['push_notification']['google_api_key'],
            'Content-Type: application/json'
        );
        $fields = array(
            'registration_ids' => $reg_ids,
            'data' => array('message' => array('notification' => $message)),
        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $config['push_notification']['google_gcm_url']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        $result = curl_exec($ch);
        if ($result === FALSE) {
            die('Curl error: ' . curl_error($ch));
        }
        curl_close($ch);
    }
}