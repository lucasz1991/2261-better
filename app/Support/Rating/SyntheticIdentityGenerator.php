<?php

namespace App\Support\Rating;

use Illuminate\Support\Str;

final class SyntheticIdentityGenerator
{
    private const AGE_RANGE_WEIGHTS = [
        '25-34' => 24,
        '35-44' => 26,
        '45-54' => 22,
        '55-64' => 18,
        '65+' => 10,
    ];

    private const NAME_SET_WEIGHTS = [
        'general' => 56,
        'turkish_german' => 12,
        'polish_german' => 8,
        'eastern_european' => 6,
        'southern_european' => 6,
        'balkan' => 5,
        'arabic_german' => 4,
        'vietnamese_german' => 3,
    ];

    private const ADDITIONAL_NAME_SETS = [
        'turkish_german' => [
            'first_names' => [
                '25-34' => ['Aylin', 'Ceren', 'Ece', 'Elif', 'Esra', 'Merve', 'Selin', 'Zeynep', 'Burak', 'Can', 'Emre', 'Kerem', 'Mert', 'Onur', 'Serkan', 'Tolga'],
                '35-44' => ['Asli', 'Aysun', 'Derya', 'Emine', 'Esra', 'Gül', 'Hatice', 'Sevgi', 'Ahmet', 'Cem', 'Hakan', 'Hasan', 'Mehmet', 'Murat', 'Ömer', 'Serkan'],
                '45-54' => ['Aynur', 'Fatma', 'Gülay', 'Gülsen', 'Hülya', 'Nesrin', 'Nuray', 'Songül', 'Ali', 'Erdal', 'Hüseyin', 'Kemal', 'Mehmet', 'Metin', 'Murat', 'Yilmaz'],
                '55-64' => ['Ayşe', 'Emine', 'Fatma', 'Gülsüm', 'Hatice', 'Nermin', 'Sultan', 'Zehra', 'Adem', 'Hasan', 'Ibrahim', 'Ismail', 'Mehmet', 'Mustafa', 'Osman', 'Yusuf'],
                '65+' => ['Ayşe', 'Emine', 'Fatma', 'Hatice', 'Meryem', 'Nuriye', 'Sultan', 'Zehra', 'Hasan', 'Hüseyin', 'Ibrahim', 'Ismail', 'Mehmet', 'Mustafa', 'Osman', 'Yusuf'],
            ],
            'last_names' => ['Acar', 'Aksoy', 'Arslan', 'Aydin', 'Çelik', 'Demir', 'Dogan', 'Erdogan', 'Kara', 'Kaya', 'Kilic', 'Koç', 'Özdemir', 'Öztürk', 'Polat', 'Şahin', 'Tekin', 'Yildirim', 'Yilmaz', 'Yüksel'],
        ],
        'polish_german' => [
            'first_names' => [
                '25-34' => ['Agnieszka', 'Aleksandra', 'Joanna', 'Julia', 'Katarzyna', 'Magdalena', 'Marta', 'Natalia', 'Jakub', 'Kamil', 'Lukasz', 'Mateusz', 'Michal', 'Pawel', 'Piotr', 'Tomasz'],
                '35-44' => ['Agnieszka', 'Anna', 'Ewa', 'Joanna', 'Katarzyna', 'Magdalena', 'Monika', 'Sylwia', 'Adam', 'Krzysztof', 'Lukasz', 'Marcin', 'Michal', 'Pawel', 'Piotr', 'Tomasz'],
                '45-54' => ['Agnieszka', 'Anna', 'Beata', 'Dorota', 'Ewa', 'Joanna', 'Katarzyna', 'Malgorzata', 'Andrzej', 'Dariusz', 'Grzegorz', 'Jacek', 'Krzysztof', 'Marek', 'Piotr', 'Robert'],
                '55-64' => ['Barbara', 'Bozena', 'Elzbieta', 'Ewa', 'Grazyna', 'Halina', 'Jolanta', 'Malgorzata', 'Andrzej', 'Janusz', 'Jerzy', 'Krzysztof', 'Marek', 'Ryszard', 'Stanislaw', 'Wojciech'],
                '65+' => ['Barbara', 'Danuta', 'Elzbieta', 'Genowefa', 'Halina', 'Irena', 'Krystyna', 'Teresa', 'Andrzej', 'Jan', 'Jerzy', 'Kazimierz', 'Ryszard', 'Stanislaw', 'Tadeusz', 'Wladyslaw'],
            ],
            'last_names' => ['Dabrowski', 'Grabowski', 'Jankowski', 'Kaczmarek', 'Kaminski', 'Kowalczyk', 'Kowalski', 'Krawczyk', 'Lewandowski', 'Mazur', 'Michalski', 'Nowak', 'Pawlak', 'Piotrowski', 'Sikora', 'Szymanski', 'Wieczorek', 'Wisniewski', 'Wojcik', 'Zielinski'],
        ],
        'eastern_european' => [
            'first_names' => [
                '25-34' => ['Alina', 'Anastasia', 'Daria', 'Elena', 'Irina', 'Katja', 'Nadja', 'Olga', 'Aleksandr', 'Andrej', 'Dimitri', 'Ilja', 'Maxim', 'Nikita', 'Roman', 'Viktor'],
                '35-44' => ['Alina', 'Elena', 'Irina', 'Jelena', 'Marina', 'Natalia', 'Olga', 'Tatjana', 'Aleksandr', 'Alexej', 'Andrej', 'Dimitri', 'Igor', 'Pavel', 'Roman', 'Viktor'],
                '45-54' => ['Elena', 'Galina', 'Irina', 'Larissa', 'Marina', 'Natalia', 'Olga', 'Svetlana', 'Aleksandr', 'Anatoli', 'Andrej', 'Igor', 'Michail', 'Oleg', 'Sergej', 'Viktor'],
                '55-64' => ['Galina', 'Irina', 'Ljudmila', 'Nadeschda', 'Natalia', 'Olga', 'Svetlana', 'Valentina', 'Anatoli', 'Boris', 'Igor', 'Michail', 'Nikolai', 'Oleg', 'Sergej', 'Viktor'],
                '65+' => ['Galina', 'Lidia', 'Ljudmila', 'Nadeschda', 'Nina', 'Raisa', 'Tamara', 'Valentina', 'Anatoli', 'Boris', 'Iwan', 'Leonid', 'Nikolai', 'Sergej', 'Viktor', 'Wladimir'],
            ],
            'last_names' => ['Alexeev', 'Ivanov', 'Karpov', 'Kozlov', 'Kuznetsov', 'Lebedev', 'Morozov', 'Orlov', 'Petrov', 'Popov', 'Romanov', 'Smirnov', 'Sokolov', 'Volkov', 'Voronov'],
        ],
        'southern_european' => [
            'first_names' => [
                '25-34' => ['Alessia', 'Chiara', 'Elena', 'Giulia', 'Lucia', 'Sara', 'Sofia', 'Valentina', 'Alessandro', 'Andrea', 'Davide', 'Luca', 'Marco', 'Matteo', 'Paolo', 'Stefano'],
                '35-44' => ['Alessandra', 'Chiara', 'Elena', 'Francesca', 'Giulia', 'Laura', 'Sara', 'Valentina', 'Alessandro', 'Andrea', 'Davide', 'Luca', 'Marco', 'Matteo', 'Paolo', 'Stefano'],
                '45-54' => ['Alessandra', 'Barbara', 'Cinzia', 'Francesca', 'Laura', 'Monica', 'Paola', 'Stefania', 'Andrea', 'Antonio', 'Claudio', 'Giuseppe', 'Marco', 'Massimo', 'Paolo', 'Roberto'],
                '55-64' => ['Angela', 'Anna', 'Carmela', 'Maria', 'Patrizia', 'Paola', 'Rita', 'Rosanna', 'Antonio', 'Franco', 'Giovanni', 'Giuseppe', 'Luigi', 'Mario', 'Salvatore', 'Vincenzo'],
                '65+' => ['Angela', 'Anna', 'Carmela', 'Concetta', 'Giuseppina', 'Maria', 'Rosa', 'Teresa', 'Antonio', 'Domenico', 'Giovanni', 'Giuseppe', 'Luigi', 'Mario', 'Salvatore', 'Vincenzo'],
            ],
            'last_names' => ['Bianchi', 'Bruno', 'Colombo', 'Conti', 'Costa', 'De Luca', 'Esposito', 'Ferrari', 'Gallo', 'Giordano', 'Greco', 'Lombardi', 'Mancini', 'Marino', 'Moretti', 'Ricci', 'Rizzo', 'Romano', 'Rossi', 'Russo'],
        ],
        'balkan' => [
            'first_names' => [
                '25-34' => ['Ana', 'Ivana', 'Jelena', 'Marija', 'Milena', 'Nina', 'Sara', 'Tamara', 'Aleksandar', 'Bojan', 'Ivan', 'Luka', 'Marko', 'Milan', 'Nikola', 'Stefan'],
                '35-44' => ['Ana', 'Danijela', 'Ivana', 'Jelena', 'Marija', 'Milena', 'Sanja', 'Tamara', 'Aleksandar', 'Bojan', 'Dejan', 'Dragan', 'Ivan', 'Marko', 'Milan', 'Nikola'],
                '45-54' => ['Biljana', 'Danijela', 'Dragana', 'Jasmina', 'Ljiljana', 'Marina', 'Snezana', 'Vesna', 'Dejan', 'Dragan', 'Goran', 'Milan', 'Miroslav', 'Nenad', 'Predrag', 'Zoran'],
                '55-64' => ['Gordana', 'Jasmina', 'Ljiljana', 'Milica', 'Radmila', 'Slavica', 'Snezana', 'Vesna', 'Dragan', 'Goran', 'Milan', 'Miroslav', 'Predrag', 'Slobodan', 'Zeljko', 'Zoran'],
                '65+' => ['Danica', 'Dragica', 'Ljiljana', 'Milica', 'Radmila', 'Ružica', 'Slavica', 'Vesna', 'Branko', 'Dragan', 'Milan', 'Milorad', 'Radovan', 'Slobodan', 'Zivko', 'Zoran'],
            ],
            'last_names' => ['Babić', 'Horvat', 'Ilić', 'Jovanović', 'Kovač', 'Kovačević', 'Marković', 'Nikolić', 'Pavlović', 'Petrović', 'Popović', 'Stanković', 'Stojanović', 'Tomić', 'Vuković'],
        ],
        'arabic_german' => [
            'first_names' => [
                '25-34' => ['Amina', 'Amira', 'Dalia', 'Lina', 'Mariam', 'Nour', 'Rania', 'Sara', 'Ahmed', 'Ali', 'Karim', 'Mahmoud', 'Omar', 'Rami', 'Samir', 'Yasin'],
                '35-44' => ['Amina', 'Amira', 'Dalia', 'Hanan', 'Mariam', 'Nadia', 'Rania', 'Samira', 'Ahmed', 'Ali', 'Hassan', 'Karim', 'Khaled', 'Omar', 'Samir', 'Tarek'],
                '45-54' => ['Amal', 'Amina', 'Hanan', 'Leila', 'Mariam', 'Nadia', 'Rania', 'Samira', 'Abdul', 'Ahmed', 'Hassan', 'Khaled', 'Mahmoud', 'Mustafa', 'Samir', 'Tarek'],
                '55-64' => ['Amal', 'Fatima', 'Hanan', 'Khadija', 'Leila', 'Mariam', 'Nadia', 'Samira', 'Abdul', 'Ahmed', 'Hassan', 'Mahmoud', 'Mohammed', 'Mustafa', 'Samir', 'Youssef'],
                '65+' => ['Aisha', 'Fatima', 'Khadija', 'Leila', 'Mariam', 'Nadia', 'Salma', 'Samira', 'Abdul', 'Ahmed', 'Hassan', 'Mahmoud', 'Mohammed', 'Mustafa', 'Said', 'Youssef'],
            ],
            'last_names' => ['Abbas', 'Abdallah', 'Alami', 'Bakir', 'Darwish', 'Haddad', 'Hamdan', 'Hassan', 'Khalil', 'Mansour', 'Nasser', 'Rahman', 'Saleh', 'Salim', 'Youssef'],
        ],
        'vietnamese_german' => [
            'first_names' => [
                '25-34' => ['Anh', 'Chi', 'Giang', 'Hoa', 'Lan', 'Linh', 'Mai', 'Trang', 'Bao', 'Duc', 'Huy', 'Khoa', 'Long', 'Minh', 'Nam', 'Tuan'],
                '35-44' => ['Anh', 'Hoa', 'Huong', 'Lan', 'Linh', 'Mai', 'Phuong', 'Trang', 'Duc', 'Hai', 'Huy', 'Long', 'Minh', 'Nam', 'Thanh', 'Tuan'],
                '45-54' => ['Hoa', 'Hong', 'Huong', 'Lan', 'Lien', 'Mai', 'Nga', 'Phuong', 'Binh', 'Duc', 'Hai', 'Hung', 'Minh', 'Son', 'Thanh', 'Tuan'],
                '55-64' => ['Hoa', 'Hong', 'Huong', 'Lan', 'Lien', 'Nga', 'Phuong', 'Thuy', 'Binh', 'Duc', 'Hai', 'Hung', 'Minh', 'Son', 'Thanh', 'Tuan'],
                '65+' => ['Hoa', 'Hong', 'Huong', 'Lan', 'Lien', 'Nga', 'Phuong', 'Thuy', 'Binh', 'Duc', 'Hai', 'Hung', 'Minh', 'Son', 'Thanh', 'Tuan'],
            ],
            'last_names' => ['Bui', 'Dang', 'Do', 'Ho', 'Huynh', 'Le', 'Ngo', 'Nguyen', 'Pham', 'Phan', 'Tran', 'Trinh', 'Vo', 'Vu'],
        ],
    ];

    private const ADDITIONAL_NAME_SET_EXPANSIONS = [
        'turkish_german' => [
            'first_names' => [
                '25-34' => ['Aleyna', 'Buse', 'Damla', 'Dilara', 'Gizem', 'Irem', 'Melis', 'Nazli', 'Alper', 'Baris', 'Berk', 'Caglar', 'Deniz', 'Eren', 'Furkan', 'Kaan'],
                '35-44' => ['Arzu', 'Burcu', 'Ebru', 'Funda', 'Özlem', 'Pinar', 'Seda', 'Sibel', 'Ali', 'Baris', 'Bülent', 'Cengiz', 'Erkan', 'Gökhan', 'Kadir', 'Volkan'],
                '45-54' => ['Arzu', 'Emel', 'Filiz', 'Funda', 'Meral', 'Nevin', 'Özlem', 'Sevil', 'Ahmet', 'Bülent', 'Cengiz', 'Halil', 'Hasan', 'Ismail', 'Mustafa', 'Recep'],
                '55-64' => ['Aysel', 'Fadime', 'Gönül', 'Havva', 'Kadriye', 'Münevver', 'Necla', 'Şerife', 'Ahmet', 'Ali', 'Halil', 'Hüseyin', 'Kemal', 'Ramazan', 'Süleyman', 'Zeki'],
                '65+' => ['Aysel', 'Fadime', 'Gönül', 'Havva', 'Kadriye', 'Münevver', 'Necla', 'Şerife', 'Adem', 'Ali', 'Halil', 'Kemal', 'Ramazan', 'Süleyman', 'Veli', 'Zeki'],
            ],
            'last_names' => ['Altin', 'Aslan', 'Atalay', 'Avci', 'Balci', 'Basar', 'Bulut', 'Candan', 'Ceylan', 'Coskun', 'Duman', 'Ekinci', 'Güneş', 'Kaplan', 'Karaca', 'Keskin', 'Kurt', 'Özkan', 'Solmaz', 'Tunç'],
        ],
        'polish_german' => [
            'first_names' => [
                '25-34' => ['Alicja', 'Emilia', 'Karolina', 'Klaudia', 'Monika', 'Patrycja', 'Paulina', 'Weronika', 'Adrian', 'Bartosz', 'Damian', 'Dawid', 'Kacper', 'Konrad', 'Marcin', 'Rafal'],
                '35-44' => ['Beata', 'Dorota', 'Justyna', 'Karolina', 'Marta', 'Paulina', 'Renata', 'Weronika', 'Artur', 'Bartosz', 'Damian', 'Jacek', 'Kamil', 'Maciej', 'Mateusz', 'Rafal'],
                '45-54' => ['Barbara', 'Elzbieta', 'Grazyna', 'Halina', 'Jolanta', 'Monika', 'Renata', 'Teresa', 'Adam', 'Artur', 'Janusz', 'Jerzy', 'Maciej', 'Ryszard', 'Slawomir', 'Wojciech'],
                '55-64' => ['Danuta', 'Irena', 'Krystyna', 'Maria', 'Renata', 'Teresa', 'Urszula', 'Zofia', 'Adam', 'Bogdan', 'Kazimierz', 'Marian', 'Tadeusz', 'Wladyslaw', 'Zbigniew', 'Zenon'],
                '65+' => ['Bozena', 'Helena', 'Jadwiga', 'Janina', 'Maria', 'Stanislawa', 'Urszula', 'Zofia', 'Bogdan', 'Edward', 'Henryk', 'Marian', 'Mieczyslaw', 'Stefan', 'Zbigniew', 'Zenon'],
            ],
            'last_names' => ['Adamski', 'Baran', 'Chmielewski', 'Czarnecki', 'Dudek', 'Gorski', 'Jablonski', 'Jaworski', 'Kalinowski', 'Kozlowski', 'Krol', 'Lis', 'Majewski', 'Makowski', 'Malinowski', 'Olszewski', 'Sobczak', 'Stepien', 'Walczak', 'Wrobel'],
        ],
        'eastern_european' => [
            'first_names' => [
                '25-34' => ['Aleksandra', 'Anna', 'Ekaterina', 'Elina', 'Jana', 'Karina', 'Kristina', 'Maria', 'Anton', 'Artjom', 'Denis', 'Kirill', 'Konstantin', 'Michail', 'Stanislav', 'Wladislaw'],
                '35-44' => ['Anastasia', 'Daria', 'Ekaterina', 'Jana', 'Karina', 'Kristina', 'Nadeschda', 'Vera', 'Anton', 'Artjom', 'Denis', 'Kirill', 'Konstantin', 'Maxim', 'Michail', 'Stanislav'],
                '45-54' => ['Alla', 'Anna', 'Ekaterina', 'Jelena', 'Ljudmila', 'Nadeschda', 'Tatjana', 'Vera', 'Boris', 'Dmitri', 'Gennadi', 'Konstantin', 'Nikolai', 'Pavel', 'Stanislav', 'Wladimir'],
                '55-64' => ['Alla', 'Anna', 'Jelena', 'Larissa', 'Marina', 'Nina', 'Raisa', 'Tatjana', 'Aleksandr', 'Andrej', 'Gennadi', 'Leonid', 'Pavel', 'Roman', 'Stanislav', 'Wladimir'],
                '65+' => ['Alla', 'Anna', 'Jelena', 'Larissa', 'Marina', 'Natalia', 'Olga', 'Svetlana', 'Aleksandr', 'Andrej', 'Gennadi', 'Igor', 'Michail', 'Oleg', 'Pavel', 'Roman'],
            ],
            'last_names' => ['Antonov', 'Baranov', 'Belov', 'Bondarenko', 'Fedorov', 'Goncharov', 'Grigoriev', 'Kovalenko', 'Kravchenko', 'Makarov', 'Melnikov', 'Pavlov', 'Shevchenko', 'Tarasov', 'Zaitsev'],
        ],
        'southern_european' => [
            'first_names' => [
                '25-34' => ['Arianna', 'Aurora', 'Beatrice', 'Camilla', 'Federica', 'Giorgia', 'Martina', 'Noemi', 'Alberto', 'Daniele', 'Federico', 'Francesco', 'Gabriele', 'Lorenzo', 'Nicola', 'Simone'],
                '35-44' => ['Arianna', 'Elisa', 'Federica', 'Giorgia', 'Ilaria', 'Martina', 'Silvia', 'Veronica', 'Alberto', 'Daniele', 'Federico', 'Francesco', 'Gabriele', 'Lorenzo', 'Nicola', 'Simone'],
                '45-54' => ['Antonella', 'Caterina', 'Daniela', 'Elisabetta', 'Gabriella', 'Patrizia', 'Rita', 'Sabrina', 'Alberto', 'Carlo', 'Daniele', 'Fabio', 'Franco', 'Giovanni', 'Luigi', 'Stefano'],
                '55-64' => ['Adriana', 'Daniela', 'Gabriella', 'Giuseppina', 'Lucia', 'Marina', 'Rosa', 'Teresa', 'Carlo', 'Claudio', 'Domenico', 'Enzo', 'Francesco', 'Massimo', 'Roberto', 'Sergio'],
                '65+' => ['Adriana', 'Caterina', 'Elena', 'Francesca', 'Lucia', 'Margherita', 'Paola', 'Rita', 'Carlo', 'Claudio', 'Enzo', 'Francesco', 'Franco', 'Massimo', 'Roberto', 'Sergio'],
            ],
            'last_names' => ['Amato', 'Barbieri', 'Basile', 'Bellini', 'Caruso', 'Coppola', 'De Angelis', 'De Santis', 'Donati', 'Farina', 'Ferraro', 'Fontana', 'Leone', 'Longo', 'Marchetti', 'Martini', 'Pellegrini', 'Santoro', 'Serra', 'Villa'],
        ],
        'balkan' => [
            'first_names' => [
                '25-34' => ['Anđela', 'Emina', 'Iva', 'Katarina', 'Lana', 'Maja', 'Tea', 'Tijana', 'Andrej', 'Filip', 'Josip', 'Matej', 'Nemanja', 'Petar', 'Saša', 'Vuk'],
                '35-44' => ['Aleksandra', 'Emina', 'Irena', 'Katarina', 'Maja', 'Nataša', 'Tea', 'Tijana', 'Filip', 'Goran', 'Josip', 'Matej', 'Nenad', 'Petar', 'Saša', 'Stefan'],
                '45-54' => ['Aleksandra', 'Gordana', 'Irena', 'Milica', 'Nataša', 'Radmila', 'Slavica', 'Zorica', 'Aleksandar', 'Bojan', 'Branko', 'Ivan', 'Nikola', 'Slobodan', 'Željko', 'Živko'],
                '55-64' => ['Biljana', 'Danica', 'Dragana', 'Irena', 'Marina', 'Nataša', 'Sanja', 'Zorica', 'Aleksandar', 'Bojan', 'Branko', 'Dejan', 'Ivan', 'Nenad', 'Nikola', 'Radovan'],
                '65+' => ['Biljana', 'Gordana', 'Jasmina', 'Marina', 'Nataša', 'Sanja', 'Sofija', 'Zorica', 'Aleksandar', 'Bojan', 'Dejan', 'Goran', 'Ivan', 'Miroslav', 'Nenad', 'Predrag'],
            ],
            'last_names' => ['Antić', 'Božić', 'Đorđević', 'Hadžić', 'Knežević', 'Lukić', 'Marić', 'Milošević', 'Perić', 'Radić', 'Savić', 'Simić', 'Šarić', 'Todorović', 'Živković'],
        ],
        'arabic_german' => [
            'first_names' => [
                '25-34' => ['Aya', 'Dana', 'Farah', 'Jana', 'Laila', 'Lamia', 'Layla', 'Yasmin', 'Adam', 'Amir', 'Bilal', 'Hamza', 'Jamal', 'Malik', 'Nabil', 'Zaid'],
                '35-44' => ['Amal', 'Aya', 'Basma', 'Farah', 'Iman', 'Lamia', 'Layla', 'Yasmin', 'Amir', 'Bilal', 'Hamza', 'Jamal', 'Malik', 'Nabil', 'Rami', 'Yasin'],
                '45-54' => ['Aisha', 'Basma', 'Fatima', 'Huda', 'Iman', 'Khadija', 'Salma', 'Yasmin', 'Ali', 'Fadi', 'Jamal', 'Mohammed', 'Nabil', 'Rami', 'Said', 'Youssef'],
                '55-64' => ['Aisha', 'Basma', 'Huda', 'Iman', 'Rania', 'Salma', 'Sanaa', 'Zahra', 'Ali', 'Fadi', 'Khaled', 'Nabil', 'Omar', 'Rami', 'Said', 'Tarek'],
                '65+' => ['Amal', 'Basma', 'Hanan', 'Huda', 'Iman', 'Rania', 'Sanaa', 'Zahra', 'Ali', 'Fadi', 'Khaled', 'Nabil', 'Omar', 'Rami', 'Samir', 'Tarek'],
            ],
            'last_names' => ['Al-Khalil', 'Ammar', 'Barakat', 'Daher', 'Farah', 'Habib', 'Hariri', 'Jaber', 'Kassem', 'Khoury', 'Masri', 'Moussa', 'Najjar', 'Ramadan', 'Saad'],
        ],
        'vietnamese_german' => [
            'first_names' => [
                '25-34' => ['An', 'Chau', 'Diep', 'Ha', 'Hanh', 'Kim', 'My', 'Ngoc', 'Dat', 'Duy', 'Hoang', 'Khanh', 'Phuc', 'Quang', 'Trung', 'Vinh'],
                '35-44' => ['Chau', 'Ha', 'Hanh', 'Hien', 'Kim', 'Loan', 'My', 'Ngoc', 'Binh', 'Dat', 'Duy', 'Hoang', 'Khanh', 'Phuc', 'Quang', 'Vinh'],
                '45-54' => ['Anh', 'Chau', 'Ha', 'Hanh', 'Hien', 'Loan', 'My', 'Ngoc', 'Dat', 'Duy', 'Hoang', 'Khanh', 'Phuc', 'Quang', 'Trung', 'Vinh'],
                '55-64' => ['Anh', 'Chau', 'Ha', 'Hanh', 'Hien', 'Loan', 'My', 'Ngoc', 'Dat', 'Duy', 'Hoang', 'Khanh', 'Phuc', 'Quang', 'Trung', 'Vinh'],
                '65+' => ['Anh', 'Chau', 'Ha', 'Hanh', 'Hien', 'Loan', 'My', 'Ngoc', 'Dat', 'Duy', 'Hoang', 'Khanh', 'Phuc', 'Quang', 'Trung', 'Vinh'],
            ],
            'last_names' => ['Cao', 'Chu', 'Dinh', 'Duong', 'Ha', 'Lam', 'Ly', 'Mai', 'Phung', 'Quach', 'Thai', 'Truong', 'Vong', 'Vuong'],
        ],
    ];

    private const FIRST_NAMES_BY_AGE = [
        '25-34' => [
            'Anna', 'Anne', 'Antonia', 'Aylin', 'Carolin', 'Charlotte', 'Clara', 'Elena',
            'Emily', 'Emma', 'Franziska', 'Hannah', 'Jana', 'Johanna', 'Julia', 'Katharina',
            'Laura', 'Lea', 'Lena', 'Lisa', 'Luisa', 'Mara', 'Marie', 'Melina', 'Nadine',
            'Nina', 'Pia', 'Sabrina', 'Sarah', 'Sophie', 'Vanessa', 'Yasemin',
            'Alexander', 'André', 'Benjamin', 'Can', 'Daniel', 'David', 'Dennis', 'Dominik',
            'Felix', 'Florian', 'Jan', 'Jonas', 'Julian', 'Kevin', 'Leon', 'Lukas', 'Marcel',
            'Marco', 'Maximilian', 'Mehmet', 'Moritz', 'Nico', 'Niklas', 'Patrick', 'Paul',
            'Philipp', 'Sebastian', 'Simon', 'Tobias', 'Tom', 'Tim',
        ],
        '35-44' => [
            'Alexandra', 'Andrea', 'Anja', 'Anna', 'Bianca', 'Carina', 'Christina', 'Daniela',
            'Eva', 'Franziska', 'Jennifer', 'Jessica', 'Julia', 'Katharina', 'Katja', 'Kerstin',
            'Laura', 'Manuela', 'Melanie', 'Miriam', 'Nadine', 'Nicole', 'Nina', 'Sandra',
            'Sarah', 'Simone', 'Stefanie', 'Susanne', 'Tanja', 'Yvonne',
            'Alexander', 'Andreas', 'Christian', 'Daniel', 'Dirk', 'Florian', 'Frank', 'Jan',
            'Jens', 'Maik', 'Marc', 'Marco', 'Markus', 'Martin', 'Matthias', 'Michael', 'Murat',
            'Oliver', 'René', 'Robert', 'Sebastian', 'Sven', 'Stefan', 'Thomas', 'Thorsten',
            'Tobias', 'Torsten',
        ],
        '45-54' => [
            'Andrea', 'Anja', 'Barbara', 'Bettina', 'Birgit', 'Claudia', 'Daniela', 'Heike',
            'Karin', 'Katja', 'Kerstin', 'Manuela', 'Martina', 'Melanie', 'Michaela', 'Monika',
            'Nicole', 'Petra', 'Sabine', 'Sandra', 'Silke', 'Simone', 'Sonja', 'Stefanie',
            'Susanne', 'Sylvia', 'Tanja', 'Ute',
            'Andreas', 'Christian', 'Dirk', 'Frank', 'Holger', 'Jens', 'Jörg', 'Jürgen',
            'Kai', 'Karsten', 'Maik', 'Marc', 'Markus', 'Martin', 'Matthias', 'Michael',
            'Olaf', 'Oliver', 'Ralf', 'Sascha', 'Stefan', 'Sven', 'Thomas', 'Thorsten',
            'Torsten', 'Uwe',
        ],
        '55-64' => [
            'Angelika', 'Barbara', 'Beate', 'Birgit', 'Brigitte', 'Christine', 'Claudia', 'Gabriele',
            'Heike', 'Ingrid', 'Karin', 'Marion', 'Martina', 'Monika', 'Petra', 'Renate',
            'Sabine', 'Silke', 'Susanne', 'Ursula', 'Ute', 'Vera',
            'Andreas', 'Bernd', 'Bernhard', 'Dieter', 'Frank', 'Gerd', 'Gerhard', 'Hans-Jürgen',
            'Harald', 'Heinz', 'Jürgen', 'Klaus', 'Manfred', 'Michael', 'Norbert', 'Peter',
            'Rainer', 'Ralf', 'Reinhard', 'Thomas', 'Udo', 'Uwe', 'Werner', 'Wolfgang',
        ],
        '65+' => [
            'Annemarie', 'Brigitte', 'Christa', 'Christel', 'Edith', 'Erika', 'Gisela', 'Helga',
            'Hildegard', 'Ingrid', 'Inge', 'Karin', 'Margot', 'Marianne', 'Monika', 'Renate',
            'Rosemarie', 'Sieglinde', 'Ursula', 'Waltraud',
            'Dieter', 'Gerhard', 'Günter', 'Hans', 'Hans-Peter', 'Heinz', 'Helmut', 'Herbert',
            'Horst', 'Joachim', 'Josef', 'Karl-Heinz', 'Klaus', 'Manfred', 'Peter', 'Rainer',
            'Reinhold', 'Rolf', 'Siegfried', 'Werner', 'Wilhelm', 'Wolfgang',
        ],
    ];

    private const GENERAL_FIRST_NAME_EXPANSIONS = [
        '25-34' => [
            'Alina', 'Alisa', 'Amelie', 'Annika', 'Ann-Kathrin', 'Celina', 'Celine', 'Chiara',
            'Denise', 'Diana', 'Elisa', 'Elisabeth', 'Eva', 'Fabienne', 'Finja', 'Jacqueline',
            'Jasmin', 'Jennifer', 'Jessica', 'Josephine', 'Kim', 'Lara', 'Larissa', 'Linda',
            'Maike', 'Michelle', 'Rebecca', 'Ricarda', 'Ronja', 'Sina', 'Theresa', 'Vivien',
            'Adrian', 'Bastian', 'Björn', 'Christopher', 'Fabian', 'Frederik', 'Hendrik',
            'Johannes', 'Joshua', 'Kai', 'Karim', 'Lennart', 'Manuel', 'Mario', 'Marvin',
            'Matthias', 'Max', 'Michael', 'Mike', 'Marius', 'Nils', 'Pascal', 'Peter', 'Robin',
            'Sascha', 'Stefan', 'Sven', 'Thomas', 'Timo', 'Yannick', 'Yannik',
        ],
        '35-44' => [
            'Anne', 'Antje', 'Bettina', 'Birgit', 'Carmen', 'Carolin', 'Claudia', 'Diana',
            'Doreen', 'Esther', 'Grit', 'Heike', 'Ines', 'Isabel', 'Jana', 'Jeanette', 'Jenny',
            'Judith', 'Juliane', 'Kristina', 'Mandy', 'Marina', 'Martina', 'Michaela', 'Ramona',
            'Rebecca', 'Sabrina', 'Silke', 'Sonja',
            'André', 'Benjamin', 'Björn', 'Carsten', 'Christoph', 'David', 'Dennis', 'Dominik',
            'Enrico', 'Fabian', 'Felix', 'Holger', 'Kai', 'Karsten', 'Lars', 'Mario', 'Marcel',
            'Mike', 'Nico', 'Nils', 'Patrick', 'Peter', 'Ralf', 'Sascha', 'Simon', 'Stephan',
            'Uwe', 'Volker',
        ],
        '45-54' => [
            'Angelika', 'Antje', 'Beate', 'Brigitte', 'Carmen', 'Christina', 'Conny', 'Cornelia',
            'Doreen', 'Gabriele', 'Ina', 'Ines', 'Iris', 'Judith', 'Marion', 'Ramona', 'Renate',
            'Rita', 'Roswitha', 'Ruth', 'Sigrid', 'Viola', 'Yvonne', 'Bärbel', 'Elke', 'Eva',
            'Gaby',
            'Achim', 'André', 'Armin', 'Axel', 'Bernd', 'Bernhard', 'Carsten', 'Detlef',
            'Dieter', 'Gerd', 'Gerhard', 'Harald', 'Heinz', 'Klaus', 'Lars', 'Manfred', 'Mario',
            'Norbert', 'Peter', 'Rainer', 'Reinhard', 'Rolf', 'Stephan', 'Udo', 'Volker',
            'Werner', 'Wolfgang',
        ],
        '55-64' => [
            'Anke', 'Bärbel', 'Christa', 'Christel', 'Cornelia', 'Doris', 'Edith', 'Elke',
            'Erika', 'Eva', 'Gisela', 'Hannelore', 'Helga', 'Hildegard', 'Inge', 'Iris',
            'Margit', 'Marianne', 'Rita', 'Roswitha', 'Ruth', 'Sigrid', 'Waltraud',
            'Achim', 'Armin', 'Axel', 'Bruno', 'Detlef', 'Günter', 'Hans', 'Hans-Peter',
            'Helmut', 'Herbert', 'Horst', 'Joachim', 'Josef', 'Karl-Heinz', 'Klaus-Dieter',
            'Kurt', 'Lothar', 'Reinhold', 'Rolf', 'Siegfried', 'Volker', 'Wilhelm', 'Willi',
        ],
        '65+' => [
            'Anneliese', 'Bärbel', 'Brunhilde', 'Charlotte', 'Dorothea', 'Elisabeth', 'Elfriede',
            'Else', 'Gerda', 'Gertrud', 'Hannelore', 'Hedwig', 'Ilse', 'Irmgard', 'Lieselotte',
            'Lore', 'Ruth', 'Sigrid', 'Thea', 'Trude', 'Wilhelmine',
            'Alfred', 'Anton', 'Bruno', 'Eberhard', 'Erich', 'Ernst', 'Franz', 'Friedrich',
            'Georg', 'Gustav', 'Hans-Dieter', 'Hermann', 'Karl', 'Kurt', 'Lothar', 'Otto',
            'Paul', 'Rudolf', 'Theodor', 'Walter', 'Willi',
        ],
    ];

    private const GENERAL_LAST_NAME_EXPANSION = [
        'Abel', 'Adam', 'Albert', 'Bachmann', 'Bartels', 'Barth', 'Bayer', 'Behrens', 'Bender',
        'Benz', 'Bock', 'Böttcher', 'Brunner', 'Conrad', 'Cordes', 'Ebert', 'Eckert', 'Ehlers',
        'Eichhorn', 'Eisele', 'Fink', 'Fleischer', 'Freitag', 'Fritz', 'Geiger', 'Gerlach',
        'Götz', 'Gruber', 'Haas', 'Hagedorn', 'Haase', 'Hansen', 'Hauser', 'Heine', 'Heinemann',
        'Heller', 'Hennig', 'Herzog', 'Hesse', 'Hübner', 'Jacob', 'Jahn', 'Kaufmann', 'Keil',
        'Kempf', 'Kirchner', 'Klose', 'Knoll', 'Kolb', 'Konrad', 'Kraft', 'Kühn', 'Kunze',
        'Kurz', 'Lenz', 'Lindner', 'Link', 'Löffler', 'Maurer', 'Metzger', 'Michel', 'Mohr',
        'Nickel', 'Opitz', 'Paul', 'Reimann', 'Reuter', 'Riedel', 'Rieger', 'Römer', 'Rose',
        'Rudolph', 'Sander', 'Schenk', 'Schilling', 'Schindler', 'Schlegel', 'Schlosser',
        'Schlüter', 'Schmid', 'Seifert', 'Stahl', 'Stark', 'Stoll', 'Strauß', 'Thiel',
        'Ullrich', 'Ulrich', 'Vetter', 'Wachter', 'Wegner', 'Weigand', 'Weiler', 'Wendt',
        'Wirth', 'Witt', 'Zahn', 'Zeller', 'Zorn',
    ];

    private const LAST_NAMES = [
        'Albrecht', 'Arnold', 'Bach', 'Bauer', 'Baumann', 'Beck', 'Becker', 'Berg', 'Berger',
        'Böhm', 'Brandt', 'Braun', 'Busch', 'Dietrich', 'Döring', 'Engel', 'Ernst', 'Fischer',
        'Förster', 'Frank', 'Franke', 'Friedrich', 'Fuchs', 'Graf', 'Groß', 'Günther', 'Hahn',
        'Hartmann', 'Heinrich', 'Herrmann', 'Hoffmann', 'Horn', 'Huber', 'Jäger', 'Jansen',
        'Jung', 'Kaiser', 'Keller', 'Klein', 'Koch', 'Köhler', 'König', 'Krause', 'Krämer',
        'Krüger', 'Kuhn', 'Lang', 'Lange', 'Lehmann', 'Lorenz', 'Ludwig', 'Maier', 'Martin',
        'Mayer', 'Meier', 'Meyer', 'Möller', 'Müller', 'Neumann', 'Otto', 'Peters', 'Pfeiffer',
        'Pohl', 'Richter', 'Roth', 'Sauer', 'Schäfer', 'Schmidt', 'Schmitt', 'Schmitz',
        'Schneider', 'Scholz', 'Schreiber', 'Schröder', 'Schubert', 'Schulte', 'Schulz',
        'Schulze', 'Schumacher', 'Schuster', 'Seidel', 'Simon', 'Sommer', 'Stein', 'Steiner',
        'Thomas', 'Vogel', 'Voigt', 'Wagner', 'Walter', 'Weber', 'Weiß', 'Werner', 'Winkler',
        'Winter', 'Wolf', 'Wolff', 'Zimmermann', 'Ziegler',
        'Aydin', 'Demir', 'Kaya', 'Özdemir', 'Öztürk', 'Şahin', 'Yildirim', 'Yilmaz',
        'Grabowski', 'Jankowski', 'Kaczmarek', 'Kowalski', 'Lewandowski', 'Nowak', 'Piotrowski',
        'Ivanov', 'Petrov', 'Popov', 'Smirnov', 'Sokolov', 'Volkov',
        'Bianchi', 'Conti', 'Ferrari', 'Marino', 'Ricci', 'Rossi', 'Russo',
        'Horvat', 'Kovač', 'Marković', 'Nikolić', 'Petrović',
        'Nguyen', 'Pham', 'Tran', 'Võ',
    ];

    private const REGIONS = [
        ['state' => 'Nordrhein-Westfalen', 'city' => 'Köln', 'postal_code_area' => '50xxx'],
        ['state' => 'Nordrhein-Westfalen', 'city' => 'Düsseldorf', 'postal_code_area' => '40xxx'],
        ['state' => 'Nordrhein-Westfalen', 'city' => 'Dortmund', 'postal_code_area' => '44xxx'],
        ['state' => 'Nordrhein-Westfalen', 'city' => 'Essen', 'postal_code_area' => '45xxx'],
        ['state' => 'Nordrhein-Westfalen', 'city' => 'Bonn', 'postal_code_area' => '53xxx'],
        ['state' => 'Nordrhein-Westfalen', 'city' => 'Münster', 'postal_code_area' => '48xxx'],
        ['state' => 'Nordrhein-Westfalen', 'city' => 'Bielefeld', 'postal_code_area' => '33xxx'],
        ['state' => 'Nordrhein-Westfalen', 'city' => 'Duisburg', 'postal_code_area' => '47xxx'],
        ['state' => 'Nordrhein-Westfalen', 'city' => 'Bochum', 'postal_code_area' => '44xxx'],
        ['state' => 'Nordrhein-Westfalen', 'city' => 'Wuppertal', 'postal_code_area' => '42xxx'],
        ['state' => 'Nordrhein-Westfalen', 'city' => 'Aachen', 'postal_code_area' => '52xxx'],
        ['state' => 'Nordrhein-Westfalen', 'city' => 'Mönchengladbach', 'postal_code_area' => '41xxx'],
        ['state' => 'Nordrhein-Westfalen', 'city' => 'Paderborn', 'postal_code_area' => '33xxx'],
        ['state' => 'Nordrhein-Westfalen', 'city' => 'Siegen', 'postal_code_area' => '57xxx'],
        ['state' => 'Bayern', 'city' => 'München', 'postal_code_area' => '80xxx'],
        ['state' => 'Bayern', 'city' => 'Nürnberg', 'postal_code_area' => '90xxx'],
        ['state' => 'Bayern', 'city' => 'Augsburg', 'postal_code_area' => '86xxx'],
        ['state' => 'Bayern', 'city' => 'Regensburg', 'postal_code_area' => '93xxx'],
        ['state' => 'Bayern', 'city' => 'Würzburg', 'postal_code_area' => '97xxx'],
        ['state' => 'Bayern', 'city' => 'Ingolstadt', 'postal_code_area' => '85xxx'],
        ['state' => 'Bayern', 'city' => 'Bamberg', 'postal_code_area' => '96xxx'],
        ['state' => 'Bayern', 'city' => 'Bayreuth', 'postal_code_area' => '95xxx'],
        ['state' => 'Bayern', 'city' => 'Landshut', 'postal_code_area' => '84xxx'],
        ['state' => 'Bayern', 'city' => 'Rosenheim', 'postal_code_area' => '83xxx'],
        ['state' => 'Baden-Württemberg', 'city' => 'Stuttgart', 'postal_code_area' => '70xxx'],
        ['state' => 'Baden-Württemberg', 'city' => 'Mannheim', 'postal_code_area' => '68xxx'],
        ['state' => 'Baden-Württemberg', 'city' => 'Karlsruhe', 'postal_code_area' => '76xxx'],
        ['state' => 'Baden-Württemberg', 'city' => 'Freiburg', 'postal_code_area' => '79xxx'],
        ['state' => 'Baden-Württemberg', 'city' => 'Heidelberg', 'postal_code_area' => '69xxx'],
        ['state' => 'Baden-Württemberg', 'city' => 'Ulm', 'postal_code_area' => '89xxx'],
        ['state' => 'Baden-Württemberg', 'city' => 'Heilbronn', 'postal_code_area' => '74xxx'],
        ['state' => 'Baden-Württemberg', 'city' => 'Reutlingen', 'postal_code_area' => '72xxx'],
        ['state' => 'Baden-Württemberg', 'city' => 'Konstanz', 'postal_code_area' => '78xxx'],
        ['state' => 'Hessen', 'city' => 'Frankfurt am Main', 'postal_code_area' => '60xxx'],
        ['state' => 'Hessen', 'city' => 'Wiesbaden', 'postal_code_area' => '65xxx'],
        ['state' => 'Hessen', 'city' => 'Kassel', 'postal_code_area' => '34xxx'],
        ['state' => 'Hessen', 'city' => 'Darmstadt', 'postal_code_area' => '64xxx'],
        ['state' => 'Hessen', 'city' => 'Offenbach am Main', 'postal_code_area' => '63xxx'],
        ['state' => 'Hessen', 'city' => 'Gießen', 'postal_code_area' => '35xxx'],
        ['state' => 'Hessen', 'city' => 'Fulda', 'postal_code_area' => '36xxx'],
        ['state' => 'Hessen', 'city' => 'Marburg', 'postal_code_area' => '35xxx'],
        ['state' => 'Niedersachsen', 'city' => 'Hannover', 'postal_code_area' => '30xxx'],
        ['state' => 'Niedersachsen', 'city' => 'Braunschweig', 'postal_code_area' => '38xxx'],
        ['state' => 'Niedersachsen', 'city' => 'Osnabrück', 'postal_code_area' => '49xxx'],
        ['state' => 'Niedersachsen', 'city' => 'Oldenburg', 'postal_code_area' => '26xxx'],
        ['state' => 'Niedersachsen', 'city' => 'Göttingen', 'postal_code_area' => '37xxx'],
        ['state' => 'Niedersachsen', 'city' => 'Wolfsburg', 'postal_code_area' => '38xxx'],
        ['state' => 'Niedersachsen', 'city' => 'Lüneburg', 'postal_code_area' => '21xxx'],
        ['state' => 'Niedersachsen', 'city' => 'Hildesheim', 'postal_code_area' => '31xxx'],
        ['state' => 'Sachsen', 'city' => 'Leipzig', 'postal_code_area' => '04xxx'],
        ['state' => 'Sachsen', 'city' => 'Dresden', 'postal_code_area' => '01xxx'],
        ['state' => 'Sachsen', 'city' => 'Chemnitz', 'postal_code_area' => '09xxx'],
        ['state' => 'Sachsen', 'city' => 'Zwickau', 'postal_code_area' => '08xxx'],
        ['state' => 'Sachsen', 'city' => 'Görlitz', 'postal_code_area' => '02xxx'],
        ['state' => 'Rheinland-Pfalz', 'city' => 'Mainz', 'postal_code_area' => '55xxx'],
        ['state' => 'Rheinland-Pfalz', 'city' => 'Koblenz', 'postal_code_area' => '56xxx'],
        ['state' => 'Rheinland-Pfalz', 'city' => 'Trier', 'postal_code_area' => '54xxx'],
        ['state' => 'Rheinland-Pfalz', 'city' => 'Ludwigshafen am Rhein', 'postal_code_area' => '67xxx'],
        ['state' => 'Rheinland-Pfalz', 'city' => 'Kaiserslautern', 'postal_code_area' => '67xxx'],
        ['state' => 'Schleswig-Holstein', 'city' => 'Kiel', 'postal_code_area' => '24xxx'],
        ['state' => 'Schleswig-Holstein', 'city' => 'Lübeck', 'postal_code_area' => '23xxx'],
        ['state' => 'Schleswig-Holstein', 'city' => 'Flensburg', 'postal_code_area' => '24xxx'],
        ['state' => 'Schleswig-Holstein', 'city' => 'Norderstedt', 'postal_code_area' => '22xxx'],
        ['state' => 'Brandenburg', 'city' => 'Potsdam', 'postal_code_area' => '14xxx'],
        ['state' => 'Brandenburg', 'city' => 'Cottbus', 'postal_code_area' => '03xxx'],
        ['state' => 'Brandenburg', 'city' => 'Brandenburg an der Havel', 'postal_code_area' => '14xxx'],
        ['state' => 'Brandenburg', 'city' => 'Frankfurt (Oder)', 'postal_code_area' => '15xxx'],
        ['state' => 'Mecklenburg-Vorpommern', 'city' => 'Rostock', 'postal_code_area' => '18xxx'],
        ['state' => 'Mecklenburg-Vorpommern', 'city' => 'Schwerin', 'postal_code_area' => '19xxx'],
        ['state' => 'Mecklenburg-Vorpommern', 'city' => 'Greifswald', 'postal_code_area' => '17xxx'],
        ['state' => 'Mecklenburg-Vorpommern', 'city' => 'Neubrandenburg', 'postal_code_area' => '17xxx'],
        ['state' => 'Sachsen-Anhalt', 'city' => 'Magdeburg', 'postal_code_area' => '39xxx'],
        ['state' => 'Sachsen-Anhalt', 'city' => 'Halle', 'postal_code_area' => '06xxx'],
        ['state' => 'Sachsen-Anhalt', 'city' => 'Dessau-Roßlau', 'postal_code_area' => '06xxx'],
        ['state' => 'Thüringen', 'city' => 'Erfurt', 'postal_code_area' => '99xxx'],
        ['state' => 'Thüringen', 'city' => 'Jena', 'postal_code_area' => '07xxx'],
        ['state' => 'Thüringen', 'city' => 'Gera', 'postal_code_area' => '07xxx'],
        ['state' => 'Thüringen', 'city' => 'Weimar', 'postal_code_area' => '99xxx'],
        ['state' => 'Saarland', 'city' => 'Saarbrücken', 'postal_code_area' => '66xxx'],
        ['state' => 'Saarland', 'city' => 'Neunkirchen', 'postal_code_area' => '66xxx'],
        ['state' => 'Berlin', 'city' => 'Berlin', 'postal_code_area' => '10xxx'],
        ['state' => 'Hamburg', 'city' => 'Hamburg', 'postal_code_area' => '20xxx'],
        ['state' => 'Bremen', 'city' => 'Bremen', 'postal_code_area' => '28xxx'],
        ['state' => 'Bremen', 'city' => 'Bremerhaven', 'postal_code_area' => '27xxx'],
    ];

    private const STATE_WEIGHTS = [
        'Nordrhein-Westfalen' => 215,
        'Bayern' => 158,
        'Baden-Württemberg' => 135,
        'Niedersachsen' => 98,
        'Hessen' => 77,
        'Rheinland-Pfalz' => 51,
        'Sachsen' => 49,
        'Berlin' => 46,
        'Schleswig-Holstein' => 36,
        'Brandenburg' => 31,
        'Sachsen-Anhalt' => 26,
        'Thüringen' => 25,
        'Hamburg' => 23,
        'Mecklenburg-Vorpommern' => 20,
        'Saarland' => 12,
        'Bremen' => 8,
    ];

    private const USERNAME_ALIASES = [
        'abendfeder', 'abendfunke', 'abendrot', 'achtsamzeit', 'ankerplatz', 'bergfeder',
        'bergfreund', 'bergpfad', 'bergzeit', 'blattwerk', 'buchfunke', 'buntgedacht',
        'farbenfroh', 'federleicht', 'fernweh', 'freiraum', 'frischebrise', 'funkenflug',
        'gartenzeit', 'gedankenflug', 'glückskind', 'goldmoment', 'grünefeder', 'hafenblick',
        'heimathafen', 'horizontblick', 'kaffeefunke', 'kaffeepause', 'klarerblick',
        'kleineauszeit', 'kopfkino', 'küstenkind', 'landluft', 'lesefuchs', 'leseratte',
        'lichtblick', 'luftsprung', 'mondfeder', 'morgenfunke', 'morgenkaffee', 'morgenlicht',
        'naturzeit', 'neustart', 'nordlicht', 'pfadfinder', 'pixelpause', 'regenbogen',
        'reiselust', 'ruhepol', 'seebrise', 'seelenruhe', 'sonnenseite', 'sternenblick',
        'stadtfuchs', 'stadtmensch', 'tagtraum', 'tassenheld', 'tiefgang', 'traumfeder',
        'uferlicht', 'waldfeder', 'waldfreund', 'waldfunke', 'waldpfad', 'waldzeit',
        'wanderfunke', 'wegbegleiter', 'wellenklang', 'wellenreiter', 'weitsicht', 'windspiel',
        'wolkenfeder', 'wolkenlos', 'wolkenpfad', 'wortfuchs', 'wortsammler', 'zeichenwind',
        'zeitreise', 'zeitfenster', 'zeitgeist', 'zeitlos', 'zuversicht',
    ];

    private const USERNAME_PSEUDONYM_MODIFIERS = [
        'achtsamer', 'bunter', 'echter', 'entspannter', 'federleichter', 'flinker', 'freier',
        'froher', 'geduldiger', 'gelassener', 'heiterer', 'heller', 'klarer', 'kreativer',
        'leiser', 'lockerer', 'mutiger', 'neugieriger', 'offener', 'ruhiger', 'sanfter',
        'sonniger', 'spontaner', 'treuer', 'wacher', 'wilder', 'zeitloser', 'zuversichtlicher',
    ];

    private const USERNAME_PSEUDONYM_SUBJECTS = [
        'anker', 'bergfink', 'buchfreund', 'dachs', 'denker', 'entdecker', 'falke', 'freigeist',
        'fuchs', 'gartenfreund', 'globus', 'hafenlotse', 'komet', 'lebenskünstler',
        'lesefreund', 'lichtsucher', 'lotse', 'morgenmensch', 'nachtdenker', 'otter',
        'pfadfinder', 'pixelpilot', 'radler', 'segler', 'sternsucher', 'spurensucher',
        'tagträumer', 'wanderer', 'wegbegleiter', 'weltenbummler', 'windreiter', 'wolkenjäger',
        'wortsammler', 'zeitreisender',
    ];

    private const USERNAME_PSEUDONYM_PATTERNS = [
        'curated_alias' => 40,
        'modifier_subject' => 42,
        'subject_modifier' => 18,
    ];

    private const USERNAME_CONTEXT_PATTERN_WEIGHTS = [
        'none' => 36,
        'random_number' => 12,
        'birth_short' => 14,
        'initials' => 10,
        'location_code' => 12,
        'initials_birth' => 6,
        'location_birth' => 6,
        'initials_location' => 4,
    ];

    private const USERNAME_SEPARATOR_WEIGHTS = [
        'compact' => 45,
        'dot' => 22,
        'underscore' => 21,
        'dash' => 12,
    ];

    private const USERNAME_LOCATION_CODES = [
        'Köln' => ['k', '0221'],
        'Düsseldorf' => ['d', '0211'],
        'Dortmund' => ['do', '0231'],
        'Essen' => ['e', '0201'],
        'Bonn' => ['bn', '0228'],
        'Münster' => ['ms', '0251'],
        'Bielefeld' => ['bi', '0521'],
        'Duisburg' => ['du', '0203'],
        'Bochum' => ['bo', '0234'],
        'Wuppertal' => ['w', '0202'],
        'Aachen' => ['ac', '0241'],
        'Mönchengladbach' => ['mg', '02161'],
        'Paderborn' => ['pb', '05251'],
        'Siegen' => ['si', '0271'],
        'München' => ['m', '089'],
        'Nürnberg' => ['n', '0911'],
        'Augsburg' => ['a', '0821'],
        'Regensburg' => ['r', '0941'],
        'Würzburg' => ['wue', '0931'],
        'Ingolstadt' => ['in', '0841'],
        'Bamberg' => ['ba', '0951'],
        'Bayreuth' => ['bt', '0921'],
        'Landshut' => ['la', '0871'],
        'Rosenheim' => ['ro', '08031'],
        'Stuttgart' => ['s', '0711'],
        'Mannheim' => ['ma', '0621'],
        'Karlsruhe' => ['ka', '0721'],
        'Freiburg' => ['fr', '0761'],
        'Heidelberg' => ['hd', '06221'],
        'Ulm' => ['ul', '0731'],
        'Heilbronn' => ['hn', '07131'],
        'Reutlingen' => ['rt', '07121'],
        'Konstanz' => ['kn', '07531'],
        'Frankfurt am Main' => ['ffm', '069'],
        'Wiesbaden' => ['wi', '0611'],
        'Kassel' => ['ks', '0561'],
        'Darmstadt' => ['da', '06151'],
        'Offenbach am Main' => ['of', '069'],
        'Gießen' => ['gi', '0641'],
        'Fulda' => ['fd', '0661'],
        'Marburg' => ['mr', '06421'],
        'Hannover' => ['h', '0511'],
        'Braunschweig' => ['bs', '0531'],
        'Osnabrück' => ['os', '0541'],
        'Oldenburg' => ['ol', '0441'],
        'Göttingen' => ['goe', '0551'],
        'Wolfsburg' => ['wob', '05361'],
        'Lüneburg' => ['lg', '04131'],
        'Hildesheim' => ['hi', '05121'],
        'Leipzig' => ['l', '0341'],
        'Dresden' => ['dd', '0351'],
        'Chemnitz' => ['c', '0371'],
        'Zwickau' => ['z', '0375'],
        'Görlitz' => ['gr', '03581'],
        'Mainz' => ['mz', '06131'],
        'Koblenz' => ['ko', '0261'],
        'Trier' => ['tr', '0651'],
        'Ludwigshafen am Rhein' => ['lu', '0621'],
        'Kaiserslautern' => ['kl', '0631'],
        'Kiel' => ['ki', '0431'],
        'Lübeck' => ['hl', '0451'],
        'Flensburg' => ['fl', '0461'],
        'Norderstedt' => ['se', '040'],
        'Potsdam' => ['p', '0331'],
        'Cottbus' => ['cb', '0355'],
        'Brandenburg an der Havel' => ['brb', '03381'],
        'Frankfurt (Oder)' => ['ffo', '0335'],
        'Rostock' => ['hro', '0381'],
        'Schwerin' => ['sn', '0385'],
        'Greifswald' => ['hgw', '03834'],
        'Neubrandenburg' => ['nb', '0395'],
        'Magdeburg' => ['md', '0391'],
        'Halle' => ['hal', '0345'],
        'Dessau-Roßlau' => ['de', '0340'],
        'Erfurt' => ['ef', '0361'],
        'Jena' => ['j', '03641'],
        'Gera' => ['g', '0365'],
        'Weimar' => ['we', '03643'],
        'Saarbrücken' => ['sb', '0681'],
        'Neunkirchen' => ['nk', '06821'],
        'Berlin' => ['b', '030'],
        'Hamburg' => ['hh', '040'],
        'Bremen' => ['hb', '0421'],
        'Bremerhaven' => ['bhv', '0471'],
    ];

    private const EMAIL_PATTERNS = [
        'first_dot_last' => 24,
        'firstlast' => 11,
        'initial_dot_last' => 10,
        'first_dot_initial' => 6,
        'last_dot_first' => 7,
        'first_dash_last' => 3,
        'first_last' => 4,
        'first_dot_last_birth_short' => 7,
        'first_birth_short' => 5,
        'last_birth_short' => 4,
        'last_initial' => 4,
        'first_dot_last_full_year' => 3,
        'first_last_number' => 4,
        'initials_number' => 4,
        'first_only_number' => 2,
        'last_first_initial' => 4,
        'alias_word' => 3,
        'full_first_last' => 3,
    ];

    private const FALLBACK_EMAIL_DOMAINS = [
        'web.de' => 18,
        'gmail.com' => 17,
        'gmx.de' => 14,
        'outlook.de' => 11,
        't-online.de' => 9,
        'freenet.de' => 6,
        'hotmail.com' => 5,
        'outlook.com' => 5,
        'icloud.com' => 5,
        'gmx.net' => 4,
        'posteo.de' => 2,
        'mailbox.org' => 2,
        'proton.me' => 2,
        'yahoo.de' => 4,
        'yahoo.com' => 2,
        'aol.com' => 2,
        'live.de' => 2,
        'mail.de' => 2,
        'arcor.de' => 2,
        'googlemail.com' => 2,
        'protonmail.com' => 2,
        'me.com' => 1,
    ];

    /**
     * @return array<string, mixed>
     */
    public function persona(array $privacySettings): array
    {
        $currentYear = (int) now()->format('Y');
        $ageRange = $this->weightedKey(self::AGE_RANGE_WEIGHTS);
        $nameSet = $this->weightedKey(self::NAME_SET_WEIGHTS);
        $firstName = $this->firstNameFor($ageRange, $nameSet);
        $lastName = $this->lastNameFor($nameSet);
        $region = $this->region();
        $birthYear = $this->birthYearFor($ageRange, $currentYear);
        $earliestCustomerYear = max($currentYear - 35, $birthYear + 18);
        $customerSinceYear = random_int(min($earliestCustomerYear, $currentYear - 1), $currentYear - 1);
        $household = $this->householdProfileFor($ageRange);
        $occupation = $this->occupationFor($ageRange);
        $digitalProfile = $this->digitalProfileFor($ageRange);

        return [
            'synthetic_marker' => '2261-better-testperson',
            'identity_version' => 5,
            'name_mode' => 'realistic',
            'username_mode' => 'pseudonym_with_optional_context',
            'name_set' => $nameSet,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'alias' => null,
            'username_alias' => $this->randomValue(self::USERNAME_ALIASES),
            'display_name' => "{$firstName} {$lastName}",
            'age_range' => $ageRange,
            'birth_year' => $birthYear,
            'region' => $region['state'],
            'city' => $region['city'],
            'postal_code_area' => $region['postal_code_area'],
            'residential_context' => $this->residentialContextFor($region),
            'household_type' => $household['household_type'],
            'household_size' => $household['household_size'],
            'children_count' => $household['children_count'],
            'marital_status' => $household['marital_status'],
            'occupation_group' => $occupation,
            'digital_affinity' => $digitalProfile['digital_affinity'],
            'preferred_contact_channel' => $digitalProfile['preferred_contact_channel'],
            'usual_claim_channel' => $digitalProfile['usual_claim_channel'],
            'insurance_experience' => $this->insuranceExperienceFor($ageRange),
            'customer_since_year' => $customerSinceYear,
            'customer_tenure_years' => $currentYear - $customerSinceYear,
            'availability_window' => $this->availabilityWindowFor($occupation),
            'device_context' => $digitalProfile['device_context'],
            'communication_style' => $this->communicationStyleFor($digitalProfile['preferred_contact_channel']),
            'review_writing_style' => $this->reviewWritingStyleFor($digitalProfile['digital_affinity']),
            'language' => 'de',
            'timezone' => 'Europe/Berlin',
            'is_named_publicly' => data_get($privacySettings, 'ratings.name_visibility', 'none') !== 'none',
            'note' => 'Fiktives internes Testprofil ohne reale Personendaten.',
        ];
    }

    public function username(array $persona, callable $exists, string $token): string
    {
        return $this->uniqueIdentifier($persona, 'username', null, $exists, $token);
    }

    public function isPseudonymUsername(array $persona, string $username): bool
    {
        return preg_match('/^[a-z0-9][a-z0-9._-]{3,41}$/', $username) === 1
            && ! $this->pseudonymRevealsPersonaName($username, $persona)
            && $this->containsKnownPseudonymBase($username);
    }

    public function email(array $persona, string $domain, callable $exists, string $token): string
    {
        return $this->uniqueIdentifier($persona, 'email', strtolower($domain), $exists, $token);
    }

    public function fallbackEmailDomain(): string
    {
        return $this->weightedKey(self::FALLBACK_EMAIL_DOMAINS);
    }

    private function uniqueIdentifier(
        array $persona,
        string $target,
        ?string $domain,
        callable $exists,
        string $token
    ): string {
        $maxAttempts = $target === 'username' ? 64 : 24;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $localPart = $this->identifierCandidate($persona, $target, $domain, $attempt);
            $candidate = $target === 'email' ? "{$localPart}@{$domain}" : $localPart;

            if (! $exists($candidate)) {
                return $candidate;
            }
        }

        if ($target === 'username') {
            return $this->uniquePseudonymFallback($persona, $exists, $token);
        }

        $seed = preg_replace('/[^a-z0-9]/', '', strtolower($token)) ?: Str::lower(Str::random(8));
        $base = $this->identifierCandidate($persona, $target, $domain, $maxAttempts);
        $localPart = $this->cleanIdentifier($base.substr($seed, 0, 6), $target, $domain);

        return "{$localPart}@{$domain}";
    }

    private function uniquePseudonymFallback(array $persona, callable $exists, string $token): string
    {
        $seed = (int) sprintf('%u', crc32($token));

        for ($attempt = 0; $attempt < 32; $attempt++) {
            $base = $this->pseudonymCandidate($persona, 64 + $attempt);
            $suffix = $this->pseudonymFallbackNumber($persona, $seed, $attempt);
            $candidate = $this->cleanIdentifier($base.$suffix, 'username', null);

            if (! $exists($candidate)) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Es konnte kein eindeutiges Pseudonym erzeugt werden.');
    }

    private function identifierCandidate(array $persona, string $target, ?string $domain, int $attempt): string
    {
        if ($target === 'username') {
            return $this->pseudonymCandidate($persona, $attempt);
        }

        $rawFirstName = trim((string) ($persona['first_name'] ?? ''));
        $primaryFirstName = preg_split('/\s+/u', $rawFirstName, 2)[0] ?? $rawFirstName;
        $first = $this->identifierComponent($primaryFirstName);
        $fullFirst = $this->identifierComponent($rawFirstName);
        $last = $this->identifierComponent((string) ($persona['last_name'] ?? ''));
        $alias = $this->identifierComponent((string) ($persona['username_alias'] ?? ''));

        if ($alias === '') {
            $alias = $this->identifierComponent($this->randomValue(self::USERNAME_ALIASES));
        }

        $firstInitial = substr($first, 0, 1);
        $lastInitial = substr($last, 0, 1);
        $birthYear = (string) ($persona['birth_year'] ?? random_int(1960, 1999));
        $birthShort = substr($birthYear, -2);
        $pattern = $this->weightedKey(self::EMAIL_PATTERNS);

        $candidate = match ($pattern) {
            'first_dot_last' => "{$first}.{$last}",
            'first_last' => "{$first}_{$last}",
            'firstlast' => "{$first}{$last}",
            'initial_dot_last' => "{$firstInitial}.{$last}",
            'first_dot_initial' => "{$first}.{$lastInitial}",
            'last_dot_first' => "{$last}.{$first}",
            'first_dash_last' => "{$first}-{$last}",
            'first_dot_last_birth_short' => "{$first}.{$last}{$birthShort}",
            'first_birth_short' => "{$first}{$birthShort}",
            'last_birth_short' => "{$last}{$birthShort}",
            'last_initial' => "{$last}.{$firstInitial}",
            'last_first_initial' => "{$last}{$firstInitial}",
            'first_last_number' => "{$first}.{$last}".random_int(2, 99),
            'alias_word' => $alias,
            'first_dot_last_full_year' => "{$first}.{$last}{$birthYear}",
            'initials_number' => "{$firstInitial}{$lastInitial}".random_int(10, 999),
            'first_only_number' => $first.random_int(2, 999),
            'full_first_last' => "{$fullFirst}.{$last}",
            default => "{$first}.{$last}",
        };

        if ($attempt >= 8) {
            $candidate .= random_int(10, $attempt >= 16 ? 9999 : 999);
        }

        return $this->cleanIdentifier($candidate, $target, $domain);
    }

    private function pseudonymCandidate(array $persona, int $attempt): string
    {
        for ($candidateAttempt = 0; $candidateAttempt < 32; $candidateAttempt++) {
            $alias = (string) ($persona['username_alias'] ?? '');

            if ($alias === '' || $candidateAttempt > 0) {
                $alias = $this->randomValue(self::USERNAME_ALIASES);
            }

            $modifier = $this->randomValue(self::USERNAME_PSEUDONYM_MODIFIERS);
            $subject = $this->randomValue(self::USERNAME_PSEUDONYM_SUBJECTS);
            $separator = $this->pseudonymSeparator();
            $pattern = $this->weightedKey(self::USERNAME_PSEUDONYM_PATTERNS);

            $candidate = match ($pattern) {
                'curated_alias' => $alias,
                'modifier_subject' => $modifier.$separator.$subject,
                'subject_modifier' => $subject.($separator !== '' ? $separator : '.').$modifier,
                default => $alias,
            };
            $context = $this->pseudonymContext($persona);

            if ($attempt >= 8 && $context === '') {
                $context = (string) $this->pseudonymNumber($persona);
            }

            if ($context !== '') {
                $candidate .= $this->pseudonymSeparator().$context;
            }

            $candidate = $this->cleanIdentifier($candidate, 'username', null);

            if ($this->isPseudonymUsername($persona, $candidate)) {
                return $candidate;
            }
        }

        foreach (self::USERNAME_ALIASES as $alias) {
            $candidate = $this->cleanIdentifier($alias.$this->pseudonymNumber($persona), 'username', null);

            if ($this->isPseudonymUsername($persona, $candidate)) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Es konnte kein Pseudonym ohne Namensbestandteile erzeugt werden.');
    }

    private function pseudonymSeparator(): string
    {
        return match ($this->weightedKey(self::USERNAME_SEPARATOR_WEIGHTS)) {
            'dot' => '.',
            'underscore' => '_',
            'dash' => '-',
            default => '',
        };
    }

    private function pseudonymContext(array $persona): string
    {
        $initials = $this->personaInitials($persona);
        $birthShort = $this->personaBirthShort($persona);
        $locationCode = $this->usernameLocationCode($persona);
        $innerSeparator = random_int(1, 100) <= 20 ? '.' : '';

        return match ($this->weightedKey(self::USERNAME_CONTEXT_PATTERN_WEIGHTS)) {
            'random_number' => (string) $this->pseudonymNumber($persona),
            'birth_short' => $birthShort,
            'initials' => $initials,
            'location_code' => $locationCode,
            'initials_birth' => $this->joinPseudonymContext([$initials, $birthShort], $innerSeparator),
            'location_birth' => $this->joinPseudonymContext([$locationCode, $birthShort], $innerSeparator),
            'initials_location' => $this->joinPseudonymContext([$initials, $locationCode], $innerSeparator),
            default => '',
        };
    }

    private function personaInitials(array $persona): string
    {
        $initials = '';

        foreach (['first_name', 'last_name'] as $field) {
            $parts = preg_split('/[\s-]+/u', trim((string) ($persona[$field] ?? ''))) ?: [];

            foreach ($parts as $part) {
                $part = $this->identifierComponent($part);

                if ($part !== '') {
                    $initials .= substr($part, 0, 1);
                }
            }
        }

        return substr($initials, 0, 3);
    }

    private function personaBirthShort(array $persona): string
    {
        $birthYear = (int) ($persona['birth_year'] ?? 0);

        return $birthYear > 0 ? substr((string) $birthYear, -2) : '';
    }

    private function usernameLocationCode(array $persona): string
    {
        $city = trim((string) ($persona['city'] ?? ''));
        $codes = self::USERNAME_LOCATION_CODES[$city] ?? [];

        if ($codes !== []) {
            return $this->randomValue($codes);
        }

        $city = $this->identifierComponent($city);

        return $city !== '' ? substr($city, 0, min(3, strlen($city))) : '';
    }

    private function joinPseudonymContext(array $parts, string $separator): string
    {
        return implode($separator, array_values(array_filter($parts, fn (string $part): bool => $part !== '')));
    }

    private function pseudonymNumber(array $persona): int
    {
        $blockedNumbers = $this->personaBlockedNumbers($persona);

        do {
            $number = random_int(2, 999);
        } while (in_array($number, $blockedNumbers, true));

        return $number;
    }

    private function pseudonymFallbackNumber(array $persona, int $seed, int $attempt): int
    {
        $blockedNumbers = $this->personaBlockedNumbers($persona);
        $number = 1000 + (($seed + ($attempt * 7919)) % 9000);

        while (in_array($number, $blockedNumbers, true)) {
            $number = 1000 + (($number - 1000 + 137) % 9000);
        }

        return $number;
    }

    /**
     * @return array<int, int>
     */
    private function personaBlockedNumbers(array $persona): array
    {
        $birthYear = (int) ($persona['birth_year'] ?? 0);

        return array_values(array_filter([
            $birthYear,
            $birthYear > 0 ? $birthYear % 100 : null,
            (int) ($persona['customer_since_year'] ?? 0),
        ]));
    }

    private function containsKnownPseudonymBase(string $candidate): bool
    {
        $candidate = $this->identifierComponent($candidate);
        $knownBases = array_merge(
            self::USERNAME_ALIASES,
            self::USERNAME_PSEUDONYM_MODIFIERS,
            self::USERNAME_PSEUDONYM_SUBJECTS
        );

        foreach ($knownBases as $knownBase) {
            if (str_contains($candidate, $this->identifierComponent($knownBase))) {
                return true;
            }
        }

        return false;
    }

    private function pseudonymRevealsPersonaName(string $candidate, array $persona): bool
    {
        $candidate = $this->identifierComponent($candidate);

        foreach ($this->personaNameComponents($persona) as $nameComponent) {
            if (str_contains($candidate, $nameComponent)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function personaNameComponents(array $persona): array
    {
        $components = [];

        foreach (['first_name', 'last_name'] as $field) {
            $rawName = trim((string) ($persona[$field] ?? ''));
            $fullName = $this->identifierComponent($rawName);

            if (strlen($fullName) >= 3) {
                $components[] = $fullName;
            }

            foreach (preg_split('/[\s-]+/u', $rawName) ?: [] as $part) {
                $part = $this->identifierComponent($part);

                if (strlen($part) >= 3) {
                    $components[] = $part;
                }
            }
        }

        return array_values(array_unique($components));
    }

    private function cleanIdentifier(string $value, string $target, ?string $domain): string
    {
        $value = Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9._-]+/', '')
            ->replaceMatches('/[.]{2,}/', '.')
            ->replaceMatches('/[_]{2,}/', '_')
            ->replaceMatches('/[-]{2,}/', '-')
            ->trim('.-_')
            ->toString();

        if ($target === 'email' && $domain === 'gmail.com') {
            $value = str_replace(['_', '-'], '.', $value);
            $value = preg_replace('/[.]{2,}/', '.', $value) ?: $value;
        }

        $limit = $target === 'email' ? 48 : 42;
        $value = substr($value, 0, $limit);

        return trim($value, '.-_') ?: 'user'.random_int(100, 9999);
    }

    private function identifierComponent(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '')
            ->toString();
    }

    private function birthYearFor(string $ageRange, int $currentYear): int
    {
        [$minAge, $maxAge] = match ($ageRange) {
            '25-34' => [25, 34],
            '35-44' => [35, 44],
            '45-54' => [45, 54],
            '55-64' => [55, 64],
            default => [65, 84],
        };

        return random_int($currentYear - $maxAge, $currentYear - $minAge);
    }

    private function firstNameFor(string $ageRange, string $nameSet): string
    {
        $names = isset(self::ADDITIONAL_NAME_SETS[$nameSet])
            ? array_merge(
                self::ADDITIONAL_NAME_SETS[$nameSet]['first_names'][$ageRange],
                self::ADDITIONAL_NAME_SET_EXPANSIONS[$nameSet]['first_names'][$ageRange]
            )
            : $this->generalFirstNamesForAge($ageRange);

        // In mehrsprachigen Familien sind deutsche Vornamen ebenfalls häufig.
        if ($nameSet !== 'general' && random_int(1, 100) <= 16) {
            $names = $this->generalFirstNamesForAge($ageRange);
        }

        return $this->randomValue($names);
    }

    /**
     * @return array<int, string>
     */
    private function generalFirstNamesForAge(string $ageRange): array
    {
        return array_values(array_unique(array_merge(
            self::FIRST_NAMES_BY_AGE[$ageRange],
            self::GENERAL_FIRST_NAME_EXPANSIONS[$ageRange]
        )));
    }

    private function lastNameFor(string $nameSet): string
    {
        $lastNames = $this->lastNamesFor($nameSet);

        // Gemischte Familiennamen verhindern zu starre Vorname-Nachname-Schablonen.
        if (random_int(1, 100) <= 14) {
            $lastNames = $this->allLastNames();
        }

        $lastName = $this->randomValue($lastNames);

        if (random_int(1, 100) <= 7) {
            $secondPool = random_int(1, 100) <= 55 ? $this->generalLastNames() : $lastNames;

            do {
                $secondName = $this->randomValue($secondPool);
            } while ($secondName === $lastName);

            return "{$lastName}-{$secondName}";
        }

        return $lastName;
    }

    /**
     * @return array<int, string>
     */
    private function lastNamesFor(string $nameSet): array
    {
        if (! isset(self::ADDITIONAL_NAME_SETS[$nameSet])) {
            return $this->generalLastNames();
        }

        return array_values(array_unique(array_merge(
            self::ADDITIONAL_NAME_SETS[$nameSet]['last_names'],
            self::ADDITIONAL_NAME_SET_EXPANSIONS[$nameSet]['last_names']
        )));
    }

    /**
     * @return array<int, string>
     */
    private function generalLastNames(): array
    {
        $firstAdditionalName = array_search('Aydin', self::LAST_NAMES, true);

        $baseNames = is_int($firstAdditionalName)
            ? array_slice(self::LAST_NAMES, 0, $firstAdditionalName)
            : self::LAST_NAMES;

        return array_values(array_unique(array_merge($baseNames, self::GENERAL_LAST_NAME_EXPANSION)));
    }

    /**
     * @return array<int, string>
     */
    private function allLastNames(): array
    {
        $lastNames = $this->generalLastNames();

        foreach (array_keys(self::ADDITIONAL_NAME_SETS) as $nameSet) {
            $lastNames = array_merge($lastNames, $this->lastNamesFor($nameSet));
        }

        return array_values(array_unique($lastNames));
    }

    /**
     * @return array{state: string, city: string, postal_code_area: string}
     */
    private function region(): array
    {
        $state = $this->weightedKey(self::STATE_WEIGHTS);
        $regions = array_values(array_filter(
            self::REGIONS,
            fn (array $region): bool => $region['state'] === $state
        ));

        return $this->randomValue($regions);
    }

    /**
     * @param  array{state: string, city: string, postal_code_area: string}  $region
     */
    private function residentialContextFor(array $region): string
    {
        $metropolitanCities = [
            'Berlin', 'Hamburg', 'München', 'Köln', 'Frankfurt am Main', 'Düsseldorf',
            'Stuttgart', 'Leipzig', 'Dortmund', 'Essen', 'Bremen',
        ];

        $weights = in_array($region['city'], $metropolitanCities, true)
            ? ['innerstädtisch' => 48, 'Stadtrand' => 35, 'Umland' => 17]
            : ['städtisch' => 46, 'Stadtrand/Umland' => 34, 'regionaler Raum' => 20];

        return $this->weightedKey($weights);
    }

    /**
     * @return array{household_type: string, household_size: int, children_count: int, marital_status: string}
     */
    private function householdProfileFor(string $ageRange): array
    {
        $weights = match ($ageRange) {
            '25-34' => ['Single-Haushalt' => 34, 'Paar ohne Kinder' => 25, 'Familie mit Kindern' => 23, 'Alleinerziehend' => 6, 'Wohngemeinschaft' => 10, 'Mehrgenerationenhaushalt' => 2],
            '35-44' => ['Single-Haushalt' => 21, 'Paar ohne Kinder' => 17, 'Familie mit Kindern' => 48, 'Alleinerziehend' => 9, 'Wohngemeinschaft' => 3, 'Mehrgenerationenhaushalt' => 2],
            '45-54' => ['Single-Haushalt' => 23, 'Paar ohne Kinder' => 29, 'Familie mit Kindern' => 32, 'Alleinerziehend' => 7, 'Wohngemeinschaft' => 2, 'Mehrgenerationenhaushalt' => 7],
            '55-64' => ['Single-Haushalt' => 30, 'Paar ohne Kinder' => 48, 'Familie mit Kindern' => 8, 'Alleinerziehend' => 3, 'Wohngemeinschaft' => 2, 'Mehrgenerationenhaushalt' => 9],
            default => ['Single-Haushalt' => 43, 'Paar ohne Kinder' => 42, 'Familie mit Kindern' => 2, 'Alleinerziehend' => 1, 'Wohngemeinschaft' => 1, 'Mehrgenerationenhaushalt' => 11],
        };
        $householdType = $this->weightedKey($weights);

        if ($householdType === 'Familie mit Kindern') {
            $children = $this->randomValue([1, 1, 1, 1, 2, 2, 2, 3]);

            return [
                'household_type' => $householdType,
                'household_size' => $children + 2,
                'children_count' => $children,
                'marital_status' => $this->weightedKey(['verheiratet' => 72, 'feste Partnerschaft' => 25, 'getrennt lebend' => 3]),
            ];
        }

        if ($householdType === 'Alleinerziehend') {
            $children = $this->randomValue([1, 1, 1, 2, 2, 3]);

            return [
                'household_type' => $householdType,
                'household_size' => $children + 1,
                'children_count' => $children,
                'marital_status' => $this->weightedKey(['ledig' => 38, 'geschieden' => 48, 'getrennt lebend' => 14]),
            ];
        }

        if ($householdType === 'Paar ohne Kinder') {
            return [
                'household_type' => $householdType,
                'household_size' => 2,
                'children_count' => 0,
                'marital_status' => $this->weightedKey(['verheiratet' => 68, 'feste Partnerschaft' => 30, 'verwitwet mit neuer Partnerschaft' => 2]),
            ];
        }

        if ($householdType === 'Wohngemeinschaft') {
            return [
                'household_type' => $householdType,
                'household_size' => random_int(2, 5),
                'children_count' => 0,
                'marital_status' => 'ledig',
            ];
        }

        if ($householdType === 'Mehrgenerationenhaushalt') {
            $children = $this->randomValue([0, 0, 0, 1, 1, 2, 2, 3]);

            return [
                'household_type' => $householdType,
                'household_size' => max(3, $children + random_int(2, 3)),
                'children_count' => $children,
                'marital_status' => $this->weightedKey(['verheiratet' => 62, 'feste Partnerschaft' => 12, 'ledig' => 14, 'verwitwet' => 12]),
            ];
        }

        $singleStatusWeights = $ageRange === '65+'
            ? ['ledig' => 22, 'geschieden' => 31, 'verwitwet' => 47]
            : ['ledig' => 56, 'geschieden' => 38, 'verwitwet' => 6];

        return [
            'household_type' => 'Single-Haushalt',
            'household_size' => 1,
            'children_count' => 0,
            'marital_status' => $this->weightedKey($singleStatusWeights),
        ];
    }

    private function occupationFor(string $ageRange): string
    {
        $weights = match ($ageRange) {
            '25-34' => ['Angestellt' => 58, 'Selbstständig' => 10, 'Öffentlicher Dienst' => 12, 'Ausbildung/Studium' => 20],
            '35-44', '45-54' => ['Angestellt' => 62, 'Selbstständig' => 15, 'Öffentlicher Dienst' => 18, 'Arbeitssuchend' => 5],
            '55-64' => ['Angestellt' => 50, 'Selbstständig' => 13, 'Öffentlicher Dienst' => 17, 'Rentner/in' => 15, 'Arbeitssuchend' => 5],
            default => ['Rentner/in' => 92, 'Angestellt' => 4, 'Selbstständig' => 2, 'Öffentlicher Dienst' => 2],
        };

        return $this->weightedKey($weights);
    }

    /**
     * @return array{digital_affinity: string, preferred_contact_channel: string, usual_claim_channel: string, device_context: string}
     */
    private function digitalProfileFor(string $ageRange): array
    {
        $affinityWeights = match ($ageRange) {
            '25-34' => ['hoch' => 62, 'mittel' => 34, 'gering' => 4],
            '35-44' => ['hoch' => 49, 'mittel' => 44, 'gering' => 7],
            '45-54' => ['hoch' => 33, 'mittel' => 52, 'gering' => 15],
            '55-64' => ['hoch' => 20, 'mittel' => 52, 'gering' => 28],
            default => ['hoch' => 10, 'mittel' => 38, 'gering' => 52],
        };
        $digitalAffinity = $this->weightedKey($affinityWeights);

        $contactWeights = match ($digitalAffinity) {
            'hoch' => ['Kundenportal' => 38, 'E-Mail' => 31, 'Telefon' => 20, 'App/Chat' => 11],
            'mittel' => ['E-Mail' => 34, 'Telefon' => 33, 'Kundenportal' => 23, 'Brief' => 10],
            default => ['Telefon' => 48, 'Brief' => 28, 'E-Mail' => 17, 'Filiale' => 7],
        };
        $claimChannelWeights = match ($digitalAffinity) {
            'hoch' => ['Online-Portal' => 45, 'App' => 17, 'E-Mail' => 20, 'Telefon' => 13, 'Makler/Vermittler' => 5],
            'mittel' => ['Online-Portal' => 27, 'E-Mail' => 25, 'Telefon' => 27, 'Makler/Vermittler' => 13, 'Filiale' => 8],
            default => ['Telefon' => 40, 'Makler/Vermittler' => 23, 'Filiale' => 18, 'Brief' => 12, 'E-Mail' => 7],
        };
        $deviceWeights = match ($digitalAffinity) {
            'hoch' => ['Smartphone' => 55, 'Notebook' => 24, 'Tablet' => 11, 'Desktop' => 10],
            'mittel' => ['Smartphone' => 35, 'Notebook' => 27, 'Desktop' => 23, 'Tablet' => 15],
            default => ['Desktop' => 35, 'Smartphone' => 27, 'Tablet' => 22, 'Notebook' => 16],
        };

        return [
            'digital_affinity' => $digitalAffinity,
            'preferred_contact_channel' => $this->weightedKey($contactWeights),
            'usual_claim_channel' => $this->weightedKey($claimChannelWeights),
            'device_context' => $this->weightedKey($deviceWeights),
        ];
    }

    private function insuranceExperienceFor(string $ageRange): string
    {
        $weights = match ($ageRange) {
            '25-34' => ['erster eigener Schadenfall' => 32, 'einzelne frühere Schadenfälle' => 51, 'mehrere Vorgänge in den letzten Jahren' => 17],
            '35-44' => ['erster eigener Schadenfall' => 18, 'einzelne frühere Schadenfälle' => 54, 'mehrere Vorgänge in den letzten Jahren' => 28],
            '45-54' => ['erster eigener Schadenfall' => 11, 'einzelne frühere Schadenfälle' => 52, 'mehrere Vorgänge in den letzten Jahren' => 37],
            default => ['erster eigener Schadenfall' => 8, 'einzelne frühere Schadenfälle' => 47, 'mehrere Vorgänge in den letzten Jahren' => 45],
        };

        return $this->weightedKey($weights);
    }

    private function availabilityWindowFor(string $occupation): string
    {
        $weights = match ($occupation) {
            'Rentner/in' => ['morgens' => 37, 'vormittags' => 31, 'nachmittags' => 24, 'abends' => 8],
            'Ausbildung/Studium' => ['morgens' => 10, 'mittags' => 17, 'nachmittags' => 31, 'abends' => 42],
            'Selbstständig' => ['früh morgens' => 12, 'mittags' => 23, 'später Nachmittag' => 31, 'abends' => 34],
            default => ['morgens' => 15, 'in der Mittagspause' => 20, 'nachmittags' => 24, 'abends' => 41],
        };

        return $this->weightedKey($weights);
    }

    private function communicationStyleFor(string $contactChannel): string
    {
        $weights = match ($contactChannel) {
            'Telefon', 'Filiale' => ['freundlich und erklärend' => 38, 'direkt und lösungsorientiert' => 36, 'detailorientiert' => 26],
            'Brief' => ['formell und ausführlich' => 49, 'sachlich strukturiert' => 40, 'knapp und bestimmt' => 11],
            'App/Chat' => ['kurz und umgangssprachlich' => 53, 'direkt und lösungsorientiert' => 34, 'freundlich mit Rückfragen' => 13],
            default => ['sachlich strukturiert' => 38, 'freundlich und ausführlich' => 32, 'kurz und direkt' => 30],
        };

        return $this->weightedKey($weights);
    }

    private function reviewWritingStyleFor(string $digitalAffinity): string
    {
        $weights = match ($digitalAffinity) {
            'hoch' => ['knapp und umgangssprachlich' => 31, 'sachlich mit Stichpunkten' => 29, 'pointiert und persönlich' => 24, 'ausführlich chronologisch' => 16],
            'mittel' => ['sachlich mit Details' => 36, 'persönlich erzählend' => 25, 'kurz und wertend' => 22, 'ausführlich chronologisch' => 17],
            default => ['ausführlich chronologisch' => 38, 'sachlich und zurückhaltend' => 34, 'persönlich erzählend' => 20, 'kurz und wertend' => 8],
        };

        return $this->weightedKey($weights);
    }

    /**
     * @template T
     *
     * @param  array<int, T>  $values
     * @return T
     */
    private function randomValue(array $values): mixed
    {
        return $values[array_rand($values)];
    }

    /**
     * @param  array<string, int>  $weights
     */
    private function weightedKey(array $weights): string
    {
        $target = random_int(1, array_sum($weights));
        $cursor = 0;

        foreach ($weights as $key => $weight) {
            $cursor += $weight;

            if ($target <= $cursor) {
                return $key;
            }
        }

        return (string) array_key_last($weights);
    }
}
