<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;

class AdminSecurityController extends AbstractController
{
    #[Route('/admin/login', name: 'admin_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('admin_dashboard');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        if ($error) {
            $this->addFlash('error', $error->getMessage());
            if ($error instanceof InvalidCsrfTokenException) {
                $this->addFlash('error', '安全令牌无效，请重试');
                $logDir = $this->getParameter('kernel.project_dir') . '/var/log';
                file_put_contents(
                    $logDir . '/csrf.log',
                    sprintf(
                        "[%s] CSRF Token 验证失败\n",
                        date('Y-m-d H:i:s')
                    ),
                    FILE_APPEND
                );
            }
            $logDir = $this->getParameter('kernel.project_dir') . '/var/log';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0777, true);
            }
            file_put_contents(
                $logDir . '/auth.log',
                date('Y-m-d H:i:s') . ' - ' . $error->getMessage() . "\n",
                FILE_APPEND
            );
        }
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('admin/security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/admin/logout', name: 'admin_logout')]
    public function logout(): void
    {
        // 会被 security.yaml 中的 logout 处理
    }
}
