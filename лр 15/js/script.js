let myString = "Привет, мир!";
let myNumber = 10;
let myArray = [1, 2, 3, 4]; 
let myObject = { name: "Иван", age: 30 };
let myDate = new Date();
let pi = Math.PI;
///////////////////////////////////////////////////////
const book = {
    title: 'Война и мир',
    author: 'Лев Толстой',
    pages: 1274,
    isFinished: true,
    usersReading: [1946, 1293, 7743]
};
///////////////////////////////////////////

document.write(book.title);
document.write('<br>');
document.write(`На полке стоит «${book['title']}»`)

// добавляем новое свойство
book.author = 'А.С.Пушкин'
document.write('<br>');
document.write(book.author);
// изменяем существующее
book.title = 'Сказка о царе Салтане'
document.write('<br>');
document.write(book.title);
/////////////////////////////////////////////

delete book.usersReading
delete book['isFinished']
document.write('<br>');
document.write(book.usersReading);
/////////////////////////////////////////////
document.write('<br>');
document.write("isFinished" in book);
document.write('<br>');
document.write("title" in book);
///////////////////////////////////////////////
for (let name in book) {
    alert(book[name]); 
}
for (let name in book) {
    alert(name);
}