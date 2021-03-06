<?php

use Hcode\Model\Cart;
use Hcode\Page;
use Hcode\Model\Order;
use Hcode\Model\User;

$app->post('/login', function () {
    try {
        User::login($_POST['login'], $_POST['password']);
    } catch (Exception $e) {
        User::setMsgError($e->getMessage());
        header('Location: /login');
        exit;
    }

    header('Location: /profile');
    exit;
});

$app->get('/logout', function () {
    User::logout();
    header('Location: /login');
    exit;
});

$app->post('/register', function () {
    if (!isset($_POST['name']) || $_POST['name'] == '') {
        User::setRegisterMsgError('Por favor preencha o campo Nome Completo.');
        header('Location: /login');
        exit;
    }

    if (!isset($_POST['email']) || $_POST['email'] == '') {
        User::setRegisterMsgError('Por favor preencha o campo email.');
        header('Location: /login');
        exit;
    }

    if (!isset($_POST['password']) || $_POST['password'] == '') {
        User::setRegisterMsgError('Por favor preencha o campo senha.');
        header('Location: /login');
        exit;
    }

    if (User::checkLoginExists($_POST['login'])) {
        User::setRegisterMsgError('O e-mail informado já está sendo usado por outro usuário.');
        header('Location: /login');
        exit;
    }

    $_SESSION['registerValues'] = $_POST;

    $user = new User();
    $user->setData([
        'inadmin' => 0,
        'deslogin' => $_POST['email'],
        'desperson' => $_POST['name'],
        'desemail' => $_POST['email'],
        'despassword' => $_POST['password'],
        'nrphone' => $_POST['phone']
    ]);

    $user->save();
    
    User::login($_POST['email'], $_POST['password']);
    header('Location: /checkout');
    exit;
});

$app->get('/forgot', function () {
    $page = new Page();
    $page->setTpl("forgot");
});

$app->post('/forgot', function () {
    $sendSucess = User::getForgot($_POST['email'], false);
    
    if ($sendSucess) {
        header("Location: /forgot/sent");
        exit;
    } else {
        header("Location: /forgot");
        exit;
    }
});

$app->get('/forgot/sent', function () {
    $page = new Page();
    $page->setTpl("forgot-sent");
});

$app->get("/forgot/reset", function () {
    $user = User::validForgotDecrypt($_GET['code']);

    $page = new Page();
    $page->setTpl("forgot-reset", array(
        "name" => $user['desperson'],
        "code" => $_GET['code']
    ));
});

$app->post("/forgot/reset", function () {
    $forgot = User::validForgotDecrypt($_POST['code']);

    User::setForgotUsed($forgot['idrecovery']);

    $user = new User();
    $user->get((int)$forgot['iduser']);
    $password = $_POST['password'];
    $user->setdespassword($password);
    $user->update();

    $page = new Page();

    $page->setTpl("forgot-reset-success");
});


$app->get("/profile", function () {
    User::verifyLogin(false);
    $user = User::getFromSession();

    $page = new Page();
    $page->setTpl('profile', [
        'user' => $user->getValues(),
        'profileMsg' => User::getSucess(),
        'profileError' => User::getMsgError()
    ]);
});

$app->post("/profile", function () {
    User::verifyLogin(false);

    if (!isset($_POST['desperson']) || $_POST['desperson'] === '') {
        User::setMsgError('Preencha seu nome.');
        header("Location: /profile");
        exit;
    }

    if (!isset($_POST['desemail']) || $_POST['desemail'] === '') {
        User::setMsgError('Preencha o campo e-mail.');
        header("Location: /profile");
        exit;
    }

    $user = User::getFromSession();

    if ($_POST['desemail'] !== $user->getdesemail()) {
        if (User::checkLoginExists($_POST['desemail']) != $user->getdesemail()) {
            User::setMsgError('Este endereço de e-mail já está cadastrado.');
            header("Location: /profile");
            exit;
        }
    }

    $_POST['inadmin'] = $user->getinadmin();
    $_POST['despassword'] = $user->getdespassword();
    $_POST['deslogin'] = $_POST['desemail'];

    $user->setData($_POST);
    $user->update();

    User::setSucess('Dados alterados com sucesso!');
    
    header("Location: /profile");
    exit;
});

$app->get('/profile/orders', function () {
    User::verifyLogin(false);

    $user = User::getFromSession();

    $page = new Page();
    $page->setTpl('profile-orders', [
        'orders' => $user->getOrders()
    ]);
});

$app->get('/profile/orders/:idorder', function ($idorder) {
    User::verifyLogin(false);

    $order = new Order();
    $order->get((int)$idorder);

    $cart = new Cart();
    $cart->get((int)$order->getidcart());
    $cart->calculateTotal();

    $page = new Page();
    $page->setTpl('profile-orders-detail', [
        'order' => $order->getValues(),
        'cart' => $cart->getValues(),
        'products' => $cart->getProducts()
    ]);
});

$app->get('/profile/change-password', function () {
    User::verifyLogin(false);

    $page = new Page();
    $page->setTpl('profile-change-password', [
        'changePassError' => User::getMsgError(),
        'changePassSuccess' => User::getSucess()
    ]);
});

$app->post('/profile/change-password', function () {
    User::verifyLogin(false);

    if (!isset($_POST['current_pass']) || $_POST['current_pass'] === '') {
        User::setMsgError('Digite a senha atual.');
        header('Location: /profile/change-password');
        exit;
    }

    if (!isset($_POST['new_pass']) || $_POST['new_pass'] === '') {
        User::setMsgError('Digite a nova senha.');
        header('Location: /profile/change-password');
        exit;
    }

    if (!isset($_POST['new_pass_confirm']) || $_POST['new_pass_confirm'] === '') {
        User::setMsgError('Confirme a nova senha.');
        header('Location: /profile/change-password');
        exit;
    }

    if ($_POST['current_pass'] === $_POST['new_pass']) {
        User::setMsgError('A nova senha deve ser diferente da antiga.');
        header('Location: /profile/change-password');
        exit;
    }

    if ($_POST['new_pass_confirm'] === $_POST['current_pass']) {
        User::setMsgError('A nova senha não coincide com a confirmação.');
        header('Location: /profile/change-password');
        exit;
    }

    $user = User::getFromSession();

    if (!password_verify($_POST['current_pass'], $user->getdespassword())) {
        User::setMsgError('A senha atual está incorreta.');
        header('Location: /profile/change-password');
        exit;
    }

    $user->setdespassword($_POST['new_pass']);
    $user->update();

    User::setSucess('Senha alterada com sucesso');
    header('Location: /profile/change-password');
    exit;
});
