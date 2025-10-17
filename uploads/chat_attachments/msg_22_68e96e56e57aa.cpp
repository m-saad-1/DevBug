
#include <iostream>
using namespace std;

class Student{
private: 
   string Name;
   int rollNo;
   float CGPA;
public:
   Student(string n, int rollNumber, float cgpa): Name(n), rollNo(rollNumber), CGPA(cgpa){ }
   
   string getName(){ return Name; }
   int getrollNo(){ return rollNo; }
   float getCGPA(){ return CGPA; }
   
   void isStudentHappy(){
       if (CGPA >= 3.5){ 
           cout << "Student is happy. " << endl; 
       }
       else {
           cout << "Student is not happy. " << endl;
       }
   }
   
   friend ostream& operator<<(ostream& out, const Student& S1);
};

ostream& operator<<(ostream& out, const Student& S1){
       out << "Name: " << S1.Name << " Roll Number: " << S1.rollNo << " CGPA: " << S1.CGPA << endl;
       
       return out;
   }
int main() {
    Student S1("Shakir", 121810, 3.2);
    cout <<  S1 << endl;
    return 0;
}