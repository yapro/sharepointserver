SharePoint server on php
================

The implementation SharePoint server on php, for sync your data calendar between your backend and microsoft outlook.


Начальная схема взаимодействия:
-------------------------------
```
1. Outlook посылает запрос получения списка (ожидает авторизацию)
2. Мы отвечаем ему запросом бэсик-авторизации
3. Outlook запрашивает логин/пароль у пользователя - пользователь вводит их
4. Outlook посылает нам запрос с логином/паролем в хедерах + повторяет пункт 1
5. Мы отвечаем ему списком GetList
6. Outlook посылает нам запрос GetListItemChangesSinceToken
7. Мы отвечаем ему списком событий + последний токен (который мы формируем)
8. Outlook посылает нам запрос GetListItemChangesSinceToken + последний токен (который мы ему послали)
9. Мы отвечаем, что ничего не изменилось
```